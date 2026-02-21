import { ref, type Ref } from 'vue'

export function useFetchState<T>(fn: (...args: any[]) => Promise<T>) {
    const loading = ref(false)
    const error = ref<any>(null)
    const success = ref(false)
    const data = ref<T | null>(null) as Ref<T | null>

    const execute = async (...args: Parameters<typeof fn>) => {
        loading.value = true
        error.value = null
        success.value = false
        try {
            const result = await fn(...args)
            data.value = result
            success.value = true
            return result
        } catch (e) {
            error.value = e
            throw e
        } finally {
            loading.value = false
        }
    }

    const reset = () => {
        loading.value = false
        error.value = null
        success.value = false
        data.value = null
    }

    return {
        loading,
        error,
        success,
        data,
        execute,
        reset,
    }
}
