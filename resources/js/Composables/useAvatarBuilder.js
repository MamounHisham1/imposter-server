import { ref, watch } from 'vue'
import { AVATAR_HEADS, AVATAR_ITEMS } from './useAvatarConfig'

const STORAGE_KEY = 'avatarBuilderState'

function loadState() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      const parsed = JSON.parse(saved)
      if (parsed.head >= AVATAR_HEADS.length) parsed.head = 0
      if (parsed.eyes >= AVATAR_ITEMS.eyes.length) parsed.eyes = -1
      if (parsed.hair >= AVATAR_ITEMS.hair.length) parsed.hair = -1
      if (parsed.beard >= AVATAR_ITEMS.beard.length) parsed.beard = -1
      return parsed
    }
  } catch (e) {}
  return { head: 0, eyes: -1, hair: -1, beard: -1 }
}

const state = ref(loadState())

watch(state, (val) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(val))
}, { deep: true })

export function useAvatarBuilder() {
  function nextItem(layer) {
    const list = AVATAR_ITEMS[layer]
    if (!list.length) return
    if (state.value[layer] < 0) state.value[layer] = 0
    else state.value[layer] = (state.value[layer] + 1) % list.length
  }

  function prevItem(layer) {
    const list = AVATAR_ITEMS[layer]
    if (!list.length) return
    if (state.value[layer] < 0) state.value[layer] = list.length - 1
    else state.value[layer] = (state.value[layer] - 1 + list.length) % list.length
  }

  function setNone(layer) {
    state.value[layer] = -1
  }

  function selectHead(index) {
    state.value.head = index
  }

  function getAvatarData() {
    return {
      head: AVATAR_HEADS[state.value.head] || AVATAR_HEADS[0],
      eyes: state.value.eyes >= 0 ? AVATAR_ITEMS.eyes[state.value.eyes] : null,
      hair: state.value.hair >= 0 ? AVATAR_ITEMS.hair[state.value.hair] : null,
      beard: state.value.beard >= 0 ? AVATAR_ITEMS.beard[state.value.beard] : null,
    }
  }

  function getCounter(layer) {
    const list = AVATAR_ITEMS[layer]
    const idx = state.value[layer]
    return idx < 0 ? `—/${list.length}` : `${idx + 1}/${list.length}`
  }

  function isNone(layer) {
    return state.value[layer] < 0
  }

  function getFilename(layer) {
    const idx = state.value[layer]
    const list = AVATAR_ITEMS[layer]
    return idx >= 0 && idx < list.length ? list[idx] : null
  }

  return {
    state,
    nextItem,
    prevItem,
    setNone,
    selectHead,
    getAvatarData,
    getCounter,
    isNone,
    getFilename,
    heads: AVATAR_HEADS,
    items: AVATAR_ITEMS,
  }
}
