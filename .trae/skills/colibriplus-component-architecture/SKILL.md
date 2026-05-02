---
name: "colibriplus-component-architecture"
description: "Guides VueJS 3 + Livewire component development in ColibriPlus patterns. Invoke when creating or modifying Vue SFC components, Livewire Blade views, TailwindCSS styling, or Pinia stores in this project."
---

# ColibriPlus Component Architecture

This skill enforces the frontend component architecture patterns of ColibriPlus, which uses Vue 3 (Composition API + SFC), TailwindCSS 4, Vite, Pinia, and Livewire 3 with Blade views.

## Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Frontend Framework | Vue 3 (SFC) | ^3.5 |
| Build Tool | Vite | ^5.4 |
| CSS Framework | TailwindCSS | ^4.1 |
| State Management | Pinia | ^2.2 |
| UI Component Library | PrimeVue | ^4.1 |
| Utility Libraries | Alpine.js, Swiper, Quill | various |
| Backend UI | Livewire 3 + Blade | ^3.6 |
| HTTP Client | Axios | ^1.7 |
| WebSockets | Laravel Echo + Reverb | ^1.17 |

## Project Structure

```
resources/
├── css/
│   ├── spa/apps/desktop/   # Desktop SPA styles
│   ├── spa/apps/mobile/    # Mobile SPA styles
│   ├── business/           # Business panel styles
│   ├── admin/              # Admin panel styles
│   ├── themes/             # Theme variables (base.css, dark/colors.css)
│   └── componented/        # Shared component styles
├── js/
│   ├── spa/
│   │   ├── apps/desktop/   # Desktop SPA entry
│   │   ├── apps/mobile/    # Mobile SPA entry
│   │   ├── kernel/env/     # Environment config
│   │   └── lang/           # Frontend i18n
│   ├── business/           # Business panel JS
│   ├── admin/              # Admin panel JS
│   ├── document/           # Document viewer JS
│   └── mpa/                # MPA utilities (charts, editor)
└── views/                  # Blade templates + Livewire views
```

## Vite Alias Configuration

```javascript
resolve: {
    alias: {
        '@': '/resources/js/spa',
        '@D': '/resources/js/spa/apps/desktop',
        '@M': '/resources/js/spa/apps/mobile',
    }
}
```

## Mandatory Component Rules

### 1. Vue 3 SFC with Composition API
All Vue components must use `<script setup>` with Composition API:

```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()

const props = defineProps({
    itemId: { type: String, required: true }
})

const emit = defineEmits(['update', 'delete'])

const isLoading = ref(false)
const data = ref(null)

const displayTitle = computed(() => data.value?.title ?? t('labels.loading'))

onMounted(async () => {
    await fetchData()
})

async function fetchData() {
    isLoading.value = true
    try {
        const response = await axios.get(`/api/items/${props.itemId}`)
        data.value = response.data
    } finally {
        isLoading.value = false
    }
}
</script>
```

### 2. TailwindCSS 4 Patterns
Use TailwindCSS 4 utility classes. The project supports dark mode via class strategy:

```html
<!-- Light/Dark mode classes -->
<div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <h1 class="text-2xl font-bold text-primary-600 dark:text-primary-400">
        {{ title }}
    </h1>
</div>
```

Dark theme CSS builds are generated separately:
- `build:dark-admin`, `build:dark-business`, `build:dark-desktop`, `build:dark-mobile`, `build:dark-auth`

### 3. Livewire Blade Views
Livewire component views go in `resources/views/livewire/` following the component namespace:

```
Livewire class: App\Livewire\Business\Ads\Upsert
View path: resources/views/livewire/business/ads/upsert.blade.php
```

### 4. Pinia Store Pattern
Pinia stores use the Composition API style (setup stores):

```javascript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useItemStore = defineStore('item', () => {
    const items = ref([])
    const isLoading = ref(false)

    const activeItems = computed(() =>
        items.value.filter(item => item.status === 'active')
    )

    async function fetchItems() {
        isLoading.value = true
        try {
            const response = await axios.get('/api/items')
            items.value = response.data
        } finally {
            isLoading.value = false
        }
    }

    return { items, isLoading, activeItems, fetchItems }
})
```

### 5. Vue Router
SPA navigation uses Vue Router 4. Routes are defined modularly:

```javascript
const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('@D/pages/HomePage.vue')
    },
    {
        path: '/profile/:username',
        name: 'profile',
        component: () => import('@D/pages/ProfilePage.vue')
    }
]
```

### 6. HTTP Requests via Axios
All API calls must use Axios with proper error handling:

```javascript
import axios from 'axios'

async function fetchData() {
    try {
        const response = await axios.get('/api/endpoint')
        return response.data
    } catch (error) {
        if (error.response?.status === 401) {
            // handle unauthorized
        }
        throw error
    }
}
```

### 7. vue-i18n Integration
Use `vue-i18n` for frontend translations:

```javascript
const { t } = useI18n()

// In template
<span>{{ t('api.button.submit') }}</span>
```

### 8. Laravel Echo + Reverb
Real-time features use Laravel Echo with Reverb WebSocket server:

```javascript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws'],
})
```

### 9. Third-Party Libraries
Use project-established libraries for specific features:
- **Media**: `@giphy/js-fetch-api` (GIFs), `recordrtc` (recording), `howler` (audio)
- **UI**: `swiper` (carousels), `apexcharts` (charts), `quill` (rich text)
- **Utils**: `autolinker` (link detection), `markdown-it` (markdown), `hotkeys-js` (keyboard shortcuts)

### 10. Component File Naming
- Vue SFC files: PascalCase (e.g., `UserProfile.vue`, `PostCard.vue`)
- Blade views: snake_case (e.g., `upsert.blade.php`, `user_profile.blade.php`)
- JS modules: camelCase (e.g., `useAuth.js`, `apiClient.js`)

## Code Generation Checklist

When generating frontend code for this project, verify:

- [ ] Vue SFC uses `<script setup>` with Composition API
- [ ] TailwindCSS 4 classes with `dark:` variants for theme support
- [ ] Livewire views placed in correct `resources/views/livewire/` path
- [ ] Pinia stores use Composition API (setup) style
- [ ] Axios used for HTTP with try/catch error handling
- [ ] vue-i18n `t()` for translatable strings
- [ ] Vite aliases (`@`, `@D`, `@M`) used for imports
- [ ] Echo/Reverb configured for real-time features
- [ ] Third-party library usage consistent with existing patterns
- [ ] File naming follows project conventions (PascalCase/camelCase/snake_case)
