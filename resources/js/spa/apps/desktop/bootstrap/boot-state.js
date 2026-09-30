/*
 * App 启动 Promise 共享：桌面路由守卫据此等待 bootstrap 完成后再判定权限。
 */
export let appBootPromise = null;

export function setAppBootPromise(promise)
{
    appBootPromise = promise;
}
