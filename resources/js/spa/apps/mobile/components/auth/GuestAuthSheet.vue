<template>
	<ActionSheet v-if="open" v-on:close="close">
		<!-- 引导步骤 -->
		<div v-if="step === 'gate'" class="px-5">
			<div class="flex-center mb-3">
				<img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="h-9">
			</div>

			<h3 class="text-center text-par-xl font-semibold text-lab-pr mb-1">
				{{ __t('auth.gate_title') }}
			</h3>
			<p class="text-center text-par-m text-lab-tr mb-6">
				{{ __t('auth.gate_caption') }}
			</p>

			<button type="button" v-on:click="step='login'"
				class="w-full rounded-xl bg-cMain text-black font-semibold py-3 mb-3">
				{{ __t('buttons.login') }}
			</button>

			<button type="button" v-on:click="goSignup"
				class="w-full rounded-xl bg-input-pr text-lab-pr font-medium py-3 mb-3">
				{{ __t('auth.gate_signup') }}
			</button>

			<button type="button" v-on:click="close"
				class="w-full text-lab-sc text-par-m py-1">
				{{ __t('auth.gate_continue') }}
			</button>
		</div>

		<!-- 内嵌登录步骤 -->
		<div v-else class="px-5">
			<h3 class="text-par-xl font-semibold text-lab-pr mb-4">
				{{ __t('buttons.login') }}
			</h3>

			<div class="mb-3">
				<input type="text" v-model="login"
					class="block w-full bg-input-pr rounded-xl border border-transparent outline-hidden text-par-m text-lab-pr px-4 py-3"
					v-bind:placeholder="__t('auth.login_or_email')">
			</div>

			<div class="mb-2">
				<input type="password" v-model="password"
					class="block w-full bg-input-pr rounded-xl border border-transparent outline-hidden text-par-m text-lab-pr px-4 py-3"
					v-bind:placeholder="__t('auth.password_label')"
					v-on:keyup.enter="submitLogin">
			</div>

			<p v-if="error" class="text-red-900 text-par-s mb-2 px-1">{{ error }}</p>

			<button type="button" v-on:click="submitLogin" v-bind:disabled="submitting"
				class="w-full rounded-xl bg-cMain text-black font-semibold py-3 mt-2 mb-3 disabled:opacity-60">
				{{ __t('buttons.login') }}
			</button>

			<div class="flex items-center justify-between text-par-m">
				<button type="button" v-on:click="step='gate'" class="text-lab-sc">
					{{ __t('auth.gate_back') }}
				</button>

				<span class="text-lab-sc">
					{{ __t('auth.gate_no_account') }}
					<button type="button" v-on:click="goSignup" class="text-cMain font-medium ml-1">
						{{ __t('auth.gate_signup_link') }}
					</button>
				</span>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, ref, onMounted, onUnmounted } from 'vue';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import { AxiosAuth } from '@/kernel/services/axios/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { toastError } from '@M/core/services/toasts/index.js';

	export default defineComponent({
		setup() {
			const authStore = useAuthStore();

			const open = ref(false);
			const step = ref('gate');
			const login = ref('');
			const password = ref('');
			const error = ref('');
			const submitting = ref(false);

			const openSheet = function() {
				// 访客触发保护功能：提示需登录并跳转到独立登录页（不再弹内嵌遮罩）。
				open.value = false;
				toastError(__t('auth.gate_caption'), 4000);

				setTimeout(function() {
					window.location.href = embedder('routes.user_auth_index');
				}, 350);
			};

			const close = function() {
				open.value = false;
			};

			const goSignup = function() {
				window.location.href = '/auth/signup';
			};

			const submitLogin = async function() {
				if (submitting.value) return;

				error.value = '';
				submitting.value = true;

				try {
					const response = await AxiosAuth.post('auth/guest-login', {
						login: login.value,
						password: password.value,
					});

					authStore.setLoginSucceeded(response.data.data.user);

					open.value = false;

					// 通知当前视图：已切换登录态，可重新拉取登录数据（无刷新）。
					colibriEventBus.emit('auth:login-succeeded');
				} catch (err) {
					if (err.response?.status === 422) {
						error.value = Object.values(err.response.data.errors || {})[0]?.[0]
							|| __t('auth.failed');
					} else {
						error.value = err.response?.data?.message || __t('auth.failed');
					}
				} finally {
					submitting.value = false;
				}
			};

			onMounted(function() {
				colibriEventBus.on('auth-gate:request', openSheet);
			});

			onUnmounted(function() {
				colibriEventBus.off('auth-gate:request', openSheet);
			});

			return {
				open, step, login, password, error, submitting,
				close, goSignup, submitLogin,
			};
		},
		components: {
			ActionSheet,
		},
	});
</script>
