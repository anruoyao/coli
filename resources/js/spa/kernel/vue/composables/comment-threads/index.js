import { ref, nextTick } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

/**
 * B站式树状评论线程（与 Flutter 端 CommentThreadController 行为对齐）：
 * - 主评论（root）分页，任意层级回复按 root_id 归并到线程；
 * - 折叠态显示服务端首屏水合的最新 2 条 preview_replies（零额外请求）；
 * - 展开后回复独立游标分页，loaded 在折叠时保留缓存，二次展开秒开；
 * - 发表 / 删除 / 表态在线程间联动。
 */
const PREVIEW_COUNT = 2;
const EXPAND_DURATION = 260;

function makeThread(root) {
    return {
        root: root,
        previews: root.relations?.preview_replies ?? [],
        loaded: [],
        total: root.replies_total ?? 0,
        expanded: false,
        loadingFirst: false,
        loadingMore: false,
        error: false,
        cursor: 0,
        hasMore: false,
        firstLoaded: false
    };
}

function dedupeById(items) {
    const seen = new Set();
    const result = [];

    for (const item of items) {
        if (! seen.has(item.id)) {
            seen.add(item.id);
            result.push(item);
        }
    }

    return result;
}

export function useCommentThreads(isGuestRef) {
    const threads = ref([]);

    const api = () => {
        return isGuestRef?.value ? colibriAPI().guest() : colibriAPI().userTimeline();
    };

    const setRoots = (roots) => {
        threads.value = roots.map(makeThread);
    };

    const appendRoots = (roots) => {
        const existing = new Set(threads.value.map((thread) => thread.root.id));

        for (const root of roots) {
            if (! existing.has(root.id)) {
                threads.value.push(makeThread(root));
            }
        }
    };

    const fetchPage = async (thread, isFirst) => {
        const flagKey = isFirst ? 'loadingFirst' : 'loadingMore';

        if (thread[flagKey]) {
            return;
        }

        thread[flagKey] = true;
        thread.error = false;

        try {
            const response = await api().params(
                isFirst ? {} : { cursor: thread.cursor }
            ).getFrom(`post/comment/${thread.root.id}/replies`);

            const incoming = response.data.data;
            const incomingTotal = response.data.meta?.total ?? thread.total;
            const merged = dedupeById(isFirst ? incoming : thread.loaded.concat(incoming));

            thread.loaded = merged;
            thread.total = incomingTotal;
            thread.firstLoaded = true;

            if (incoming.length) {
                thread.cursor = Math.min(...incoming.map((item) => item.id));
            }

            thread.hasMore = merged.length < incomingTotal && incoming.length > 0;
        } catch (error) {
            thread.error = true;
        } finally {
            thread[flagKey] = false;
        }
    };

    const toggleExpand = async (thread) => {
        if (! thread.expanded) {
            thread.expanded = true;

            if (! thread.firstLoaded) {
                if (thread.total > thread.previews.length) {
                    // 乐观展开：即使首屏请求失败，底部「点击重试」也能再次发起
                    thread.hasMore = true;
                    await fetchPage(thread, true);
                } else {
                    // 预览即全部回复，直接转正，无需请求
                    thread.loaded = thread.previews.slice();
                    thread.hasMore = false;
                    thread.firstLoaded = true;
                }
            }
        } else {
            // 折叠保留 loaded 缓存
            thread.expanded = false;
        }
    };

    const loadMore = (thread) => {
        if (thread.loadingMore || ! thread.hasMore) {
            return Promise.resolve();
        }

        return fetchPage(thread, false);
    };

    /**
     * 新评论归位。返回 false 表示找不到所属线程，调用方可保守整页重载。
     */
    const applyCreated = (comment) => {
        if (! comment.parent_id) {
            threads.value.unshift(makeThread(comment));
            return true;
        }

        const thread = threads.value.find((item) => item.root.id === comment.root_id);

        if (! thread) {
            return false;
        }

        thread.total += 1;
        thread.previews = [comment].concat(thread.previews).slice(0, PREVIEW_COUNT);

        if (thread.firstLoaded && ! thread.loaded.some((item) => item.id === comment.id)) {
            thread.loaded = [comment].concat(thread.loaded);
        }

        thread.hasMore = thread.loaded.length < thread.total;
        return true;
    };

    /**
     * 删除评论：主评论整棵线程移除；回复从预览/已加载列表中摘除。
     */
    const applyDeleted = (commentId) => {
        const rootIndex = threads.value.findIndex((thread) => thread.root.id === commentId);

        if (rootIndex !== -1) {
            threads.value.splice(rootIndex, 1);
            return 'root';
        }

        for (const thread of threads.value) {
            const existedInPreview = thread.previews.some((item) => item.id === commentId);
            const existedInLoaded = thread.loaded.some((item) => item.id === commentId);

            thread.previews = thread.previews.filter((item) => item.id !== commentId);
            thread.loaded = thread.loaded.filter((item) => item.id !== commentId);

            if (existedInPreview || existedInLoaded) {
                thread.total = Math.max(0, thread.total - 1);

                // 折叠态预览被删后，用已加载缓存补齐，保证尽量展示 2 条
                if (! thread.expanded && thread.firstLoaded && thread.previews.length < PREVIEW_COUNT) {
                    thread.previews = thread.loaded.slice(0, PREVIEW_COUNT);
                }

                thread.hasMore = thread.loaded.length < thread.total;
                return 'reply';
            }
        }

        return null;
    };

    const findComment = (commentId) => {
        for (const thread of threads.value) {
            if (thread.root.id === commentId) {
                return thread.root;
            }

            const hit = thread.previews.find((item) => item.id === commentId)
                || thread.loaded.find((item) => item.id === commentId);

            if (hit) {
                return hit;
            }
        }

        return null;
    };

    return {
        threads: threads,
        setRoots: setRoots,
        appendRoots: appendRoots,
        toggleExpand: toggleExpand,
        loadMore: loadMore,
        applyCreated: applyCreated,
        applyDeleted: applyDeleted,
        findComment: findComment
    };
}

const prefersReducedMotion = () => {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
};

/**
 * 展开/折叠高度动画：配合 flush:'pre' 的 watch（DOM 打补丁前）同步捕获旧高度，
 * nextTick 后测量新高度，用 WAAPI 在两者间插值，等价于 Flutter AnimatedSize。
 *
 * 用法（在组件中）：
 *   const bodyRef = ref(null);
 *   const heightSwap = useHeightSwap(bodyRef);
 *   watch(() => props.thread.expanded, heightSwap);
 */
export function useHeightSwap(targetRef) {
    return () => {
        const el = targetRef.value;

        if (! el || prefersReducedMotion()) {
            return;
        }

        // watcher 默认 flush:'pre'：此刻 DOM 仍是旧内容
        const from = el.offsetHeight;

        nextTick().then(() => {
            const to = el.offsetHeight;

            if (from === to) {
                return;
            }

            el.style.overflow = 'hidden';

            const animation = el.animate(
                [
                    { height: `${from}px` },
                    { height: `${to}px` }
                ],
                {
                    duration: EXPAND_DURATION,
                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)'
                }
            );

            const cleanup = () => {
                el.style.overflow = '';
            };

            animation.finished.then(cleanup).catch(cleanup);
        });
    };
}
