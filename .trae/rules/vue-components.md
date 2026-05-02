---
alwaysApply: false
globs: "resources/js/**/*.vue"
---

# ColibriPlus Vue Component Rules

## Vue 组件规范

### 必须遵守
1. 使用 `<script setup>` 语法
2. Composition API（ref, computed, onMounted 等）
3. `defineProps()` 声明 props（带类型和必填标记）
4. `defineEmits()` 声明事件
5. TailwindCSS 4 类名 + `dark:` 变体
6. `vue-i18n` 的 `t()` 用于翻译文本
7. Axios 用于 HTTP 请求
8. try/catch 包裹异步操作
9. Vite 别名导入：`@`, `@D`, `@M`
10. Pinia store 使用 setup 风格

### 示例
```vue
<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const props = defineProps({
    itemId: { type: String, required: true }
})
const emit = defineEmits(['updated'])
</script>
```
