<template>
	<div class="block">
		<p class="text-par-m text-lab-pr2" v-bind:class="clamped ? 'line-clamp-2' : ''" v-html="(replyTo ? $t('labels.reply_to_prefix', {name: replyTo}) : '') + $mdInline(commentContent)"></p>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			commentContent: {
				type: String,
				required: true
			},
			// 回复目标用户名：存在时正文前渲染「回复 @name：」前缀
			replyTo: {
				type: String,
				default: ''
			},
			// 折叠预览态：正文最多 2 行
			clamped: {
				type: Boolean,
				default: false
			}
		}
	});
</script>
