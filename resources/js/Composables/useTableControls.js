import { computed, ref, watch } from 'vue';

export function useTableControls(items, fields = []) {
    const search = ref('');
    const perPage = ref(10);
    const page = ref(1);

    const source = computed(() => (typeof items === 'function' ? items() : items) ?? []);

    const filtered = computed(() => {
        const term = search.value.trim().toLowerCase();
        if (!term) {
            return source.value;
        }

        return source.value.filter((item) =>
            fields.some((field) => String(resolve(item, field) ?? '').toLowerCase().includes(term)),
        );
    });

    const total = computed(() => filtered.value.length);
    const lastPage = computed(() => Math.max(Math.ceil(total.value / Number(perPage.value || 10)), 1));
    const from = computed(() => (total.value === 0 ? 0 : (page.value - 1) * Number(perPage.value) + 1));
    const to = computed(() => Math.min(page.value * Number(perPage.value), total.value));
    const rows = computed(() => filtered.value.slice(from.value - 1, to.value));

    watch([search, perPage, source], () => {
        page.value = 1;
    });

    watch(lastPage, () => {
        if (page.value > lastPage.value) {
            page.value = lastPage.value;
        }
    });

    function next() {
        page.value = Math.min(page.value + 1, lastPage.value);
    }

    function previous() {
        page.value = Math.max(page.value - 1, 1);
    }

    return { search, perPage, page, rows, total, from, to, lastPage, next, previous };
}

function resolve(item, path) {
    return String(path)
        .split('.')
        .reduce((value, key) => value?.[key], item);
}
