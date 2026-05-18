import { ref } from 'vue';

const ownedElements = ref(new Set());
const ownedCostumes = ref(new Set());
let loaded = false;

export function useShop() {
    async function fetchInventory() {
        try {
            const resp = await fetch('/api/inventory');
            if (!resp.ok) return;
            const data = await resp.json();
            ownedElements.value = new Set(data.elements || []);
            ownedCostumes.value = new Set(data.costumes || []);
            loaded = true;
        } catch {}
    }

    function ownsItem(filename) {
        return ownedElements.value.has(filename);
    }

    function ownsCostume(costumeId) {
        return ownedCostumes.value.has(costumeId);
    }

    return {
        ownedElements,
        ownedCostumes,
        fetchInventory,
        ownsItem,
        ownsCostume,
        loaded: () => loaded,
    };
}
