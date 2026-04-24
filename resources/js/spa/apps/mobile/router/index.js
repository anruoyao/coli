import { createRouter, createWebHistory } from 'vue-router';

import { Layouts } from '@M/core/constants/layouts.js';

const Router = createRouter({
	history: createWebHistory(),
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return { top: savedPosition.top };
        }
    },
	routes: [
        {
            path: '/wallet',
            component: function() {
                return import('@M/views/wallet/WalletIndex.vue');
            },
            alias: '/wallet',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'wallet_index',
        },
		{
			path: '/',
			component: () => {
				return import('@M/views/home/HomeIndex.vue');
			},
			alias: '/home',
            meta: {
                layout: Layouts.MAIN,
                auth: true
            },
            name: 'home_index'
		},
        {
			path: '/messenger',
			component: function() {
                return import('@M/views/messenger/MessengerIndex.vue');
            },
			alias: '/messenger',
            meta: {
                layout: Layouts.MESSENGER,
                auth: true
            },
            name: 'messenger_index',
            redirect: { 
                name: 'messenger_inbox'
            },
            children: [
                {
                    path: 'inbox',
                    component: function() {
                        return import('@M/views/messenger/children/inbox/MessengerInbox.vue');
                    },
                    name: 'messenger_inbox'
                },
                {
                    path: 'c/:chat_id',
                    component: function() {
                        return import('@M/views/messenger/children/chat/MessengerChat.vue');
                    },
                    name: 'messenger_chat'
                },
                {
                    path: 'group/:chat_id/show',
                    component: function() {
                        return import('@M/views/messenger/children/group/MessengerGroup.vue');
                    },
                    name: 'messenger_group',
                    props: true,
                },
                {
                    path: 'groups',
                    component: function() {
                        return import('@M/views/messenger/children/chat/MessengerChat.vue');
                    },
                    name: 'messenger_groups_page'
                },
                {
                    path: 'archived',
                    component: function() {
                        return import('@M/views/messenger/children/chat/MessengerChat.vue');
                    },
                    name: 'messenger_archived_page'
                }
            ]
		},
        {
			path: '/settings',
			component: function() {
                return import('@M/views/settings/SettingsIndex.vue');
            },
			alias: '/settings',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true,
                hideNavbar: true
            },
            name: 'settings_index',
            redirect: {
                name: 'settings_navigator'
            },
            children: [
                {
                    path: 'nav',
                    component: function() {
                        return import('@M/views/settings/children/navigators/SettingsNav.vue');
                    },
                    name: 'settings_navigator'
                },
                {
                    path: 'account-settings',
                    component: function() {
                        return import('@M/views/settings/children/account/AccountSettings.vue');
                    },
                    name: 'settings_account'
                },
                {
                    path: 'notifications',
                    component: function() {
                        return import('@M/views/settings/children/navigators/notifications/NotificationSettings.vue');
                    },
                    name: 'settings_notifications'
                },
                {
                    path: 'push-notifications',
                    component: function() {
                        return import('@M/views/settings/children/push_notifications/PushNotifications.vue');
                    },
                    name: 'settings_push_notifications'
                },
                {
                    path: 'email-notifications',
                    component: function() {
                        return import('@M/views/settings/children/email_notifications/EmailNotifications.vue');
                    },
                    name: 'settings_email_notifications'
                },
                {
                    path: 'language',
                    component: function() {
                        return import('@M/views/settings/children/language/LanguageSettings.vue');
                    },
                    name: 'settings_language'
                },
                {
                    path: 'theme',
                    component: function() {
                        return import('@M/views/settings/children/theme/ThemeSettings.vue');
                    },
                    name: 'settings_theme'
                },
                {
                    path: 'actions',
                    component: function() {
                        return import('@M/views/settings/children/actions/ActionSettings.vue');
                    },
                    name: 'settings_actions'
                },
            ]
		},
		{
			path: '/publication/:hash_id',
			component: function() {
                return import('@M/views/publication/PublicationIndex.vue');
            },
            props: true,
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'publication_index'
		},
        {
			path: '/@:id([a-zA-Z0-9._]+)',
			component: function() {
                return import('@M/views/profile/ProfilePage.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'profile_index',
            props: true,
            redirect: { name: 'profile_posts' },
            children: [
                {
                    path: 'posts:?',
                    component: function() {
                        return import('@M/views/profile/parts/tabs/ProfilePosts.vue');
                    },
                    name: 'profile_posts'
                },
                {
                    path: 'media',
                    component: function() {
                        return import('@M/views/profile/parts/tabs/ProfileMedia.vue');
                    },
                    name: 'profile_media'
                },
                {
                    path: 'info',
                    component: function() {
                        return import('@M/views/profile/parts/tabs/ProfileInfo.vue');
                    },
                    name: 'profile_info'
                },
            ]
		},
        {
            path: '/new/post',
            name: 'post_editor',
            component: function() {
                return import('@M/views/editors/post/PostEditor.vue');
            },
            meta: {
                layout: Layouts.POST_EDITOR,
            }
        },
        {
            path: '/new/story',
            name: 'story_editor',
            component: function() {
                return import('@M/views/editors/stories/StoriesEditor.vue');
            },
            meta: {
                layout: Layouts.FLAT,
            }
        },
        {
            path: '/stories/:story_uuid',
            component: function() {
                return import('@M/views/stories/StoriesIndex.vue');
            },
            name: 'stories_index',
            meta: {
                layout: Layouts.FLAT,
                auth: true
            },
            props: true
        },
        {
			path: '/explore',
            name: 'explore_index',
			component: function() {
                return import('@M/views/explore/ExploreIndex.vue');
            },
            redirect: {
                name: 'explore_posts'
            },
            props: true,
            children: [
                {
                    path: 'posts',
                    component: function() {
                        return import('@M/views/explore/children/posts/ExplorePosts.vue');
                    },
                    name: 'explore_posts'
                },
                {
                    path: 'people',
                    component: function() {
                        return import('@M/views/explore/children/people/ExplorePeople.vue');
                    },
                    name: 'explore_people'
                }
            ],
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            }
		},
        {
			path: '/bookmarks',
			component: function() {
                return import('@M/views/bookmarks/BookmarksIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true,
            },
            name: 'bookmarks_index'
		},
        {
            path: '/bootstrap-error',
            name: 'bootstrap_error',
            component: function() {
                return import('@M/views/errors/bootstrap/BootstrapError.vue');
            },
            meta: {
                layout: Layouts.FLAT,
                auth: false
            }
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'error_404',
            component: function() {
                return import('@M/views/errors/err404/Error404.vue');
            },
			meta: {
                layout: Layouts.FLAT,
                auth: true
            }
        },
	]
});

export default Router;