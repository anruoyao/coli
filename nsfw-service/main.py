"""
NSFW 检测微服务（NudeNet 3.x / ONNX Runtime）

仅监听 127.0.0.1，供 Laravel 队列 Job 通过 HTTP 调用。
- GET  /health     健康检查（后台连通性测试用）
- POST /v1/detect  multipart: file + type(image|video)
                   图片直接检测；视频用 ffmpeg 抽帧（每 2 秒 1 帧，上限 30 帧）后逐帧检测
                   判定逻辑（阈值/触发标签）在 Laravel 侧，本服务只返回原始 detections
"""

import shutil
import subprocess
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.concurrency import run_in_threadpool
from nudenet import NudeDetector

app = FastAPI(title="nsfw-detection")

detector = NudeDetector()

# 视频抽帧参数：每 2 秒 1 帧、最多 30 帧
VIDEO_FPS = "0.5"
VIDEO_MAX_FRAMES = 30


@app.get("/health")
def health():
    return {"ok": True, "model": "320n"}


def _detect_frames(frame_paths):
    """逐帧检测，detections 扁平返回并带 frame 序号。"""
    detections = []
    for index, frame_path in enumerate(frame_paths):
        for item in detector.detect(str(frame_path)):
            detections.append({
                "label": item["class"],
                "score": round(float(item["score"]), 4),
                "box": item["box"],
                "frame": index,
            })
    return detections


def _extract_video_frames(video_path: Path, output_dir: Path):
    """ffmpeg 抽帧，返回帧文件列表。"""
    command = [
        "ffmpeg", "-hide_banner", "-loglevel", "error",
        "-i", str(video_path),
        "-vf", f"fps={VIDEO_FPS}",
        "-frames:v", str(VIDEO_MAX_FRAMES),
        "-q:v", "2",
        str(output_dir / "frame_%03d.jpg"),
    ]
    result = subprocess.run(command, capture_output=True, text=True, timeout=300)
    if result.returncode != 0:
        raise RuntimeError(f"ffmpeg failed: {result.stderr.strip()[:500]}")
    return sorted(output_dir.glob("frame_*.jpg"))


@app.post("/v1/detect")
async def detect(file: UploadFile = File(...), type: str = Form("image")):
    if type not in ("image", "video"):
        raise HTTPException(status_code=422, detail=f"unsupported type: {type}")

    suffix = Path(file.filename or "").suffix or (".mp4" if type == "video" else ".jpg")
    with tempfile.TemporaryDirectory(prefix="nsfw_") as tmp_dir:
        tmp_path = Path(tmp_dir) / f"input{suffix}"
        with tmp_path.open("wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        try:
            if type == "image":
                raw = await run_in_threadpool(detector.detect, str(tmp_path))
                detections = [
                    {"label": item["class"], "score": round(float(item["score"]), 4),
                     "box": item["box"], "frame": 0}
                    for item in raw
                ]
                return {"ok": True, "media_type": "image", "frames_checked": 1,
                        "detections": detections}

            frames_dir = Path(tmp_dir) / "frames"
            frames_dir.mkdir()
            frames = await run_in_threadpool(_extract_video_frames, tmp_path, frames_dir)
            if not frames:
                return {"ok": False, "error": "no frames extracted from video"}
            detections = await run_in_threadpool(_detect_frames, frames)
            return {"ok": True, "media_type": "video", "frames_checked": len(frames),
                    "detections": detections}
        except HTTPException:
            raise
        except Exception as exception:
            raise HTTPException(status_code=422, detail=str(exception)[:500])
