<template>
    <div class="flex flex-col gap-3">
        <div class="block">
            <RouterLink v-bind:to="{ name: 'home_index' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="home-smile" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.home') }}
                    </span>
                </div>
            </RouterLink>
        </div>

        <div class="block">
            <RouterLink v-bind:to="{ name: 'explore_posts' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center"  v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="hash-02"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.explore') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        <div class="block" v-if="$config('features.marketplace.enabled')">
            <RouterLink v-bind:to="{ name: 'marketplace_index' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="shopping-bag-03" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.marketplace') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        <div class="block" v-if="$config('features.jobs.enabled')">
            <RouterLink v-bind:to="{ name: 'jobs_index' }" v-slot="{ isActive }" class="block">
                <div  class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="briefcase-01" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.jobs') }}
                    </span>
                </div>
            </RouterLink>
        </div>

        <div class="block pl-icon-normal pr-6">
            <span class="block bg-bord-sc h-px mx-3"></span>
        </div>
        <div class="block">
            <a v-bind:href="$getRoute('user_auth_index')" class="flex items-center sidenav-inactive">
                <span class="size-icon-normal shrink-0">
                    <SvgIcon name="log-in-01" type="line"></SvgIcon>
                </span>
                <span class="ml-3 text-[19px]">
                    {{ $t('labels.login') }}
                </span>
            </a>
        </div>
        <div class="block">
            <a v-bind:href="$getRoute('user_auth_signup')" class="flex items-center sidenav-inactive">
                <span class="size-icon-normal shrink-0">
                    <SvgIcon name="user-plus-01" type="line"></SvgIcon>
                </span>
                <span class="ml-3 text-[19px]">
                    {{ $t('labels.signup') }}
                </span>
            </a>
        </div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        setup: function() {
            return {};
        }
    });
</script>
