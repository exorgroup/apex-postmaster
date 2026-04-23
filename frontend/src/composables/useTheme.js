import { ref, watch } from 'vue'

const isDark = ref(true)

export function useTheme() {
  const initTheme = () => {
    const stored = localStorage.getItem('theme')
    if (stored) {
      isDark.value = stored === 'dark'
    } else {
      isDark.value = true
    }
    applyTheme()
  }

  const applyTheme = () => {
    const root = document.documentElement
    if (isDark.value) {
      root.classList.add('app-dark')
      root.classList.remove('app-light')
    } else {
      root.classList.remove('app-dark')
      root.classList.add('app-light')
    }
  }

  const toggleTheme = () => {
    isDark.value = !isDark.value
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
    applyTheme()
  }

  const setTheme = (dark) => {
    isDark.value = dark
    localStorage.setItem('theme', dark ? 'dark' : 'light')
    applyTheme()
  }

  watch(isDark, applyTheme)

  return {
    isDark,
    initTheme,
    toggleTheme,
    setTheme
  }
}

export default useTheme
