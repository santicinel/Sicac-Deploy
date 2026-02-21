import { defineStore } from "pinia"

export type Layout = "app" | "auth" | "simple"

export const useLayoutStore = defineStore("layout", {
  state: () => ({
    currentLayout: "auth" as Layout,
  }),
  getters: {
    layout: (state) => state.currentLayout,
  },
  actions: {
    setLayout(layout: Layout) {
      this.currentLayout = layout
    },
  },
})
