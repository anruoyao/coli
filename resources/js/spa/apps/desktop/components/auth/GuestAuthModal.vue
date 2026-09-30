<template>
	<Teleport to="body">
		<Backdrop v-if="open" v-on:click.self="close">
			<div class="flex min-h-full items-center justify-center p-4">
				<PrimaryTransition>
					<div class="popup-background-tr w-[400px] rounded-2xl p-6 relative">
						<button type="button" v-on:click="close"
							class="absolute top-4 right-4 size-icon-normal text-lab-sc hover:text-lab-pr">
							<SvgIcon name="x-close" type="line" classes="size-full"></SvgIcon>
						</button>

						<!-- 引导步骤 -->
						<template v-if="step === 'gate'">
							<div class="flex-center mb-3">
								<img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="h-8">
							</div>

							<h3 class="text-par-xl font-semibold text-lab-pr mb-1 text-center">
								{{ __t('auth.gate_title') }}
							</h3>
							<p class="text-par-m text-lab-tr mb-6 text-center">
								{{ __t('auth.gate_caption') }}
							</p>

							<button type="button" v-on:click="step='login'"
								class="w-full rounded-xl bg-cMain text-black font-semibold py-3 mb-3">
								{{ __t('buttons.login') }}
							</button>

							<button type="button" v-on:click="goSignup"
								class="w-full rounded-xl bg-input-pr text-lab-pr font-medium py-3">
								{{ __t('auth.gate_signup') }}
							</button>
						</template>

						<!-- 登录步骤 -->
						<template v-else>
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
						</template>
					</div>
				</PrimaryTransition>
			</div>
		</Backdrop>
	</Teleport>
</template>

<script>
	import { defineComponent, ref, onMounted, onUnmounted } from 'vue';

	import Backdrop from '@D/components/general/modals/Backdrop.vue';
	import PrimaryTransition from '@D/components/general/transitions/PrimaryTransition.vue';
	import { AxiosAuth } from '@/kernel/services/axios/index.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	export default defineComponent({
		setup() {
			const authStore = useAuthStore();

			const open = ref(false);
			const step = ref('gate');
			const login = ref('');
			const password = ref('');
			const error = ref('');
			const submitting = ref(false);

			const openModal = function() {
				step.value = 'gate';
				error.value = '';
				login.value = '';
				password.value = '';
				open.value = true;
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
				colibriEventBus.on('auth-gate:request', openModal);
			});

			onUnmounted(function() {
				colibriEventBus.off('auth-gate:request', openModal);
			});

			return {
				open, step, login, password, error, submitting,
				close, goSignup, submitLogin,
			};
		},
		components: {
			Backdrop,
			PrimaryTransition,
		},
	});
</script>
