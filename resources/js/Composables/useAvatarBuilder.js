import { ref, computed, watch } from 'vue'
import { AVATAR_HEADS, AVATAR_ITEMS, AVATAR_GENDER, AVATAR_PAID, AVATAR_COSTUMES } from './useAvatarConfig'

const STORAGE_KEY = 'avatarBuilderState'

function loadState() {
  const fresh = () => ({ head: 0, gender: 'male', male: { eyes: null, hair: null, beard: null }, female: { eyes: null, hair: null, beard: null }, activeCostume: null })
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      const parsed = JSON.parse(saved)
      if (parsed.head >= AVATAR_HEADS.length) parsed.head = 0
      if (!parsed.male || !parsed.female) {
        const migrated = fresh()
        migrated.head = parsed.head || 0
        const gender = parsed.gender || 'male'
        for (const layer of ['eyes', 'hair', 'beard']) {
          if (typeof parsed[layer] === 'number' && parsed[layer] >= 0) {
            const list = AVATAR_ITEMS[layer]
            if (parsed[layer] < list.length) {
              migrated[gender][layer] = list[parsed[layer]]
            }
          }
        }
        return migrated
      }
      return parsed
    }
  } catch (e) {}
  return fresh()
}

const state = ref(loadState())

// Ownership data (set by Home.vue after fetching from server)
const ownedElements = ref(new Set())
const ownedCostumes = ref(new Set())

watch(state, (val) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(val))
}, { deep: true })

function getFilteredItems(layer) {
  const gender = state.value.gender
  const genderMap = AVATAR_GENDER[layer] || {}
  return AVATAR_ITEMS[layer].filter(item => {
    const g = genderMap[item]
    return !g || g === gender
  })
}

function isPaid(filename) {
  return AVATAR_PAID && AVATAR_PAID[filename] !== undefined
}

function isLocked(filename) {
  return isPaid(filename) && !ownedElements.value.has(filename)
}

export function useAvatarBuilder() {
  const filteredItems = computed(() => ({
    eyes: getFilteredItems('eyes'),
    hair: getFilteredItems('hair'),
    beard: getFilteredItems('beard'),
  }))

  function getGenderSelections() {
    return state.value[state.value.gender] || { eyes: null, hair: null, beard: null }
  }

  function getLayerIndex(layer) {
    const filename = getGenderSelections()[layer]
    if (!filename) return -1
    const list = filteredItems.value[layer]
    const idx = list.indexOf(filename)
    return idx >= 0 ? idx : -1
  }

  function nextItem(layer) {
    if (state.value.activeCostume) return
    const list = filteredItems.value[layer]
    if (!list.length) return
    const cur = getLayerIndex(layer)
    let next = cur < 0 ? 0 : (cur + 1) % list.length
    // Skip locked items
    let attempts = list.length
    while (isLocked(list[next]) && attempts > 0) {
      next = (next + 1) % list.length
      attempts--
    }
    if (attempts <= 0) return
    getGenderSelections()[layer] = list[next]
  }

  function prevItem(layer) {
    if (state.value.activeCostume) return
    const list = filteredItems.value[layer]
    if (!list.length) return
    const cur = getLayerIndex(layer)
    let prev = cur < 0 ? list.length - 1 : (cur - 1 + list.length) % list.length
    // Skip locked items
    let attempts = list.length
    while (isLocked(list[prev]) && attempts > 0) {
      prev = (prev - 1 + list.length) % list.length
      attempts--
    }
    if (attempts <= 0) return
    getGenderSelections()[layer] = list[prev]
  }

  function setNone(layer) {
    if (state.value.activeCostume) return
    getGenderSelections()[layer] = null
  }

  function selectHead(index) {
    if (state.value.activeCostume) return
    state.value.head = index
  }

  function setGender(g) {
    state.value.gender = g
  }

  function getAvatarData() {
    if (state.value.activeCostume) {
      const costume = (AVATAR_COSTUMES || []).find(c => c.id === state.value.activeCostume)
      if (costume) {
        return {
          head: costume.head,
          eyes: costume.items?.eyes || null,
          hair: costume.items?.hair || null,
          beard: costume.items?.beard || null,
        }
      }
    }
    const sel = getGenderSelections()
    return {
      head: AVATAR_HEADS[state.value.head] || AVATAR_HEADS[0],
      eyes: sel.eyes,
      hair: sel.hair,
      beard: sel.beard,
    }
  }

  function getCounter(layer) {
    const list = filteredItems.value[layer]
    const idx = getLayerIndex(layer)
    return idx < 0 ? `—/${list.length}` : `${idx + 1}/${list.length}`
  }

  function isNone(layer) {
    return !getGenderSelections()[layer]
  }

  function getFilename(layer) {
    return getGenderSelections()[layer] || null
  }

  function getItemStatus(layer, filename) {
    if (!filename) return 'free'
    if (!isPaid(filename)) return 'free'
    if (ownedElements.value.has(filename)) return 'owned'
    return 'locked'
  }

  function selectCostume(costumeId) {
    const costume = (AVATAR_COSTUMES || []).find(c => c.id === costumeId)
    if (!costume) return
    state.value.activeCostume = costumeId
  }

  function clearCostume() {
    state.value.activeCostume = null
  }

  const isCostumeLocked = computed(() => !!state.value.activeCostume)

  function updateOwnership(elements, costumes) {
    ownedElements.value = new Set(elements || [])
    ownedCostumes.value = new Set(costumes || [])
  }

  return {
    state,
    nextItem,
    prevItem,
    setNone,
    selectHead,
    setGender,
    getAvatarData,
    getCounter,
    isNone,
    getFilename,
    heads: AVATAR_HEADS,
    items: AVATAR_ITEMS,
    filteredItems,
    getItemStatus,
    isLocked,
    isPaid,
    selectCostume,
    clearCostume,
    isCostumeLocked,
    updateOwnership,
    ownedElements,
    ownedCostumes,
  }
}
