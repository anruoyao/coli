import { AxiosAuth } from '@/kernel/services/axios/index.js';

/**
 * 媒体上传大小限制（本地预检测）。
 *
 * 上传前从 `GET /api/system/upload/limits` 拉取后台配置的限制（KB），
 * 用本地文件字节数与 `max * 1024` 对比，超出立即提示，避免无谓上传。
 *
 * 网络异常降级：
 *  - 拉取失败时使用内置默认值（与后端默认配置一致），本地检测仍生效；
 *  - 文案优先使用后端返回的统一文案，拉取失败时回退到内置文案（与后端一致）。
 */
const DEFAULT_LIMITS_KB = {
    image: 128 * 1024,
    video: 512 * 1024,
    audio: 24 * 1024,
    gif: 2 * 1024,
    document: 24 * 1024,
};

const FALLBACK_MESSAGES = {
    image: (mb) => `图片文件大小不能超过 ${mb} MB`,
    video: (mb) => `视频文件大小不能超过 ${mb} MB`,
    audio: (mb) => `音频文件大小不能超过 ${mb} MB`,
    gif: (mb) => `GIF 文件大小不能超过 ${mb} MB`,
    document: (mb) => `文档文件大小不能超过 ${mb} MB`,
};

let cachedLimits = null;
let fetchPromise = null;

const fetchLimits = async () => {
    if (cachedLimits) return cachedLimits;
    if (fetchPromise) return fetchPromise;

    fetchPromise = AxiosAuth.get('/system/upload/limits')
        .then(({ data }) => {
            cachedLimits = data?.data ?? null;
            return cachedLimits;
        })
        .catch(() => {
            cachedLimits = null;
            return null;
        })
        .finally(() => {
            fetchPromise = null;
        });

    return fetchPromise;
};

/**
 * 获取某媒体类型的限制信息：{ max (KB), max_mb (MB), message }。
 * 拉取失败时返回内置默认值（message 为空，由 checkFileSize 回退内置文案）。
 */
const getLimit = async (type) => {
    const limits = await fetchLimits();
    if (limits && limits[type]) return limits[type];

    const maxKb = DEFAULT_LIMITS_KB[type] || DEFAULT_LIMITS_KB.image;
    return {
        max: maxKb,
        max_mb: Math.round(maxKb / 1024),
        message: null,
    };
};

/**
 * 本地文件大小检测。
 * @param {File|Blob} file 待上传文件（使用 .size 字节数）
 * @param {'image'|'video'|'audio'|'gif'|'document'} type 媒体类型
 * @returns {Promise<{ok: boolean, message?: string}>} ok=true 可上传；否则 message 为统一超限提示
 */
const checkFileSize = async (file, type) => {
    if (!file || !file.size) return { ok: true };

    const limit = await getLimit(type);
    if (file.size <= limit.max * 1024) return { ok: true };

    const maxMb = limit.max_mb || Math.round(limit.max / 1024);
    return {
        ok: false,
        message: limit.message || (FALLBACK_MESSAGES[type] || FALLBACK_MESSAGES.image)(maxMb),
    };
};

export { checkFileSize, getLimit };
