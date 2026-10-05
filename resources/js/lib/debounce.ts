import { ref, watch, type Ref } from 'vue';

export function refDebounced<T>(source: Ref<T>, delay: number): Ref<T> {
    const value = ref(source.value) as Ref<T>;
    let timer: ReturnType<typeof setTimeout> | undefined;

    watch(source, (next) => {
        clearTimeout(timer);
        timer = setTimeout(() => (value.value = next), delay);
    });

    return value;
}
