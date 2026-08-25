<template>
	<div class="fixed inset-0 z-[9999] bg-bg-pr flex-center">
		<div class="text-center max-w-md px-8">
			<div class="mx-auto w-12 mb-6">
				<img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="w-full">
			</div>

			<h2 class="text-title-1 font-bold text-lab-pr2 mb-3">{{ isZh ? '系统维护中' : 'Maintenance in progress' }}</h2>

			<p class="text-par-n text-lab-sc leading-relaxed mb-2">
				{{ message || (isZh ? '我们正在进行维护，请稍后再试。感谢您的耐心等待。' : 'We are performing maintenance. Please try again later. Thank you for your patience.') }}
			</p>

			<p v-if="until" class="text-par-s text-lab-tr">
				{{ isZh ? ('预计恢复：' + until) : ('Expected recovery: ' + until) }}
			</p>

			<p class="mt-6 text-par-s text-lab-tr">
				{{ isZh ? '服务恢复后将自动继续使用，无需刷新' : 'The site will resume automatically once maintenance ends.' }}
			</p>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref } from 'vue';

	/**
	 * 全局维护遮罩（移动端）：由公共频道 `App.Commands` 上的 `main.command` 指令驱动。
	 */
	export default defineComponent({
		name: 'MaintenanceOverlay',
		props: {
			message: {
				type: String,
				default: ''
			},
			until: {
				type: String,
				default: ''
			}
		},
		setup: function() {
			const isZh = ref((navigator.language || 'en').toLowerCase().indexOf('zh') !== -1);

			return {
				isZh
			};
		}
	});
</script>