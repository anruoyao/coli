<template>
	<div class="fixed inset-0 z-[10000] bg-bg-pr flex-center">
		<div class="text-center max-w-md px-8">
			<div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center mb-8" style="background: rgba(239,68,68,.10); border: 1.5px solid rgba(239,68,68,.35);">
				<svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 2 1 21h22L12 2z"></path>
					<line x1="12" y1="9" x2="12" y2="14"></line>
					<circle cx="12" cy="17.5" r="0.45" fill="#ef4444"></circle>
				</svg>
			</div>

			<h2 class="text-title-1 font-bold text-lab-pr2 mb-3">{{ title }}</h2>

			<p class="text-par-n text-lab-sc leading-relaxed mb-2">
				{{ reason || defaultDesc }}
			</p>

			<div class="flex flex-col gap-3 mt-8 items-center">
				<a v-if="supportEmail" :href="'mailto:' + supportEmail + '?subject=' + encodeURIComponent(supportSubject)" class="inline-block px-6 py-3 rounded-full text-lab-pr font-medium" style="border: 1px solid rgba(255,255,255,.16);">
					{{ contactText }}
				</a>
				<button class="inline-block px-8 py-3 rounded-full text-white font-semibold cursor-pointer" style="background:#ef4444;" @click="logout">
					{{ logoutText }}
				</button>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';
	import { useAuthStore } from '@D/store/auth/auth.store.js';

	/**
	 * 账号封禁/停用全屏提示：由用户私有频道 `main.command(banned/suspended)` 实时驱动。
	 */
	export default defineComponent({
		name: 'UserBlockedOverlay',
		props: {
			status: {
				type: String,
				default: ''
			},
			reason: {
				type: String,
				default: ''
			}
		},
		setup: function(props) {
			const authStore = useAuthStore();
			const browserLocale = (window.BackendEmbeds?.locale || navigator.language || 'en').toLowerCase();
			const isZh = computed(() => browserLocale.indexOf('zh') !== -1);
			const isJa = computed(() => browserLocale.indexOf('ja') !== -1);
			const suspended = computed(() => props.status === 'suspended');

			const supportEmail = ref(window.__SUPPORT_EMAIL__ || '');

			const title = computed(() => {
				if (suspended.value) {
					if (isZh.value) return '账号已被停用';
					return isJa.value ? 'アカウントは停止されました' : 'Account suspended';
				}
				if (isZh.value) return '账号已被封禁';
				return isJa.value ? 'アカウントはブロックされました' : 'Account blocked';
			});

			const defaultDesc = computed(() => {
				if (isZh.value) {
					return '您的账号因违反社区规范已被' + (suspended.value ? '停用' : '封禁') + '，无法继续使用平台。如有疑问，请联系客服申诉。';
				}
				if (isJa.value) {
					return 'アカウントはコミュニティガイドライン違反により' + (suspended.value ? '停止' : 'ブロック') + 'され、プラットフォームを利用できません。ご不明な点はサポートまでお問い合わせください。';
				}
				return 'Your account has been ' + (suspended.value ? 'suspended' : 'blocked') + ' for violating our community guidelines. Contact support if you have questions.';
			});

			const contactText = computed(() => isZh.value ? '联系客服申诉' : (isJa.value ? 'サポートに問い合わせる' : 'Contact support'));
			const logoutText = computed(() => isZh.value ? '退出登录' : (isJa.value ? 'ログアウト' : 'Log out'));
			const supportSubject = computed(() => {
				if (isZh.value) return '账号' + (suspended.value ? '停用' : '封禁') + '申诉';
				if (isJa.value) return 'アカウント' + (suspended.value ? '停止' : 'ブロック') + 'の異議申し立て';
				return 'Account appeal';
			});

			const logout = async () => {
				await authStore.logoutUser();
				window.location.href = embedder('routes.user_auth_index');
			};

			return {
				title,
				defaultDesc,
				contactText,
				logoutText,
				supportSubject,
				supportEmail,
				logout
			};
		}
	});
</script>