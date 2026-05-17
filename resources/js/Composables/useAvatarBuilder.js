import { ref, computed, watch } from 'vue'
import { AVATAR_HEADS, AVATAR_ITEMS, AVATAR_GENDER } from './useAvatarConfig'

const STORAGE_KEY = 'avatarBuilderState'

function loadState() {
  const fresh = () => ({ head: 0, gender: 'male', male: { eyes: null, hair: null, beard: null }, female: { eyes: null, hair: null, beard: null } })
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      const parsed = JSON.parse(saved)
      if (parsed.head >= AVATAR_HEADS.length) parsed.head = 0
      // Migrate old format (index-based) to new format (filename-based per gender)
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
    const list = filteredItems.value[layer]
    if (!list.length) return
    const cur = getLayerIndex(layer)
    const next = cur < 0 ? 0 : (cur + 1) % list.length
    getGenderSelections()[layer] = list[next]
  }

  function prevItem(layer) {
    const list = filteredItems.value[layer]
    if (!list.length) return
    const cur = getLayerIndex(layer)
    const prev = cur < 0 ? list.length - 1 : (cur - 1 + list.length) % list.length
    getGenderSelections()[layer] = list[prev]
  }

  function setNone(layer) {
    getGenderSelections()[layer] = null
  }

  function selectHead(index) {
    state.value.head = index
  }

  function setGender(g) {
    state.value.gender = g
  }

  function getAvatarData() {
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
  }
}
