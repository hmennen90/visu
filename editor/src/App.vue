<template>
  <div class="app">
    <MenuBar />

    <div class="main">
      <!-- Left panel: Layers + Entity/Tileset palette -->
      <div class="sidebar-left">
        <div class="panel layers-panel">
          <LayerPanel />
        </div>
        <div class="panel bottom-palette">
          <EntityPalette v-if="isEntityLayerActive" />
          <TilesetPanel v-else />
        </div>
      </div>

      <!-- Center: Canvas -->
      <EditorCanvas />

      <!-- Right panel: Inspector -->
      <div class="sidebar-right">
        <InspectorPanel />
      </div>
    </div>

    <div class="status-bar">
      <span v-if="store.world">
        {{ store.world.meta.name }}
        &nbsp;·&nbsp;
        {{ store.world.layers.length }} layers
        &nbsp;·&nbsp;
        Tile: {{ store.world.meta.tileSize }}px
        &nbsp;·&nbsp;
        <span v-if="store.isDirty" class="dirty">Unsaved changes</span>
        <span v-else class="saved">Saved</span>
      </span>
      <span v-else class="no-world">No world open</span>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useWorldStore } from './stores/world.js'
import MenuBar from './components/MenuBar.vue'
import LayerPanel from './components/LayerPanel.vue'
import EntityPalette from './components/EntityPalette.vue'
import TilesetPanel from './components/TilesetPanel.vue'
import EditorCanvas from './components/EditorCanvas.vue'
import InspectorPanel from './components/InspectorPanel.vue'

const store = useWorldStore()

const isEntityLayerActive = computed(() =>
  store.activeLayer?.type === 'entity'
)

onMounted(() => store.fetchConfig())
</script>

<style scoped>
.app {
  display: flex; flex-direction: column; width: 100%; height: 100%;
  overflow: hidden;
}
.main {
  flex: 1; display: flex; overflow: hidden;
}
.sidebar-left {
  width: 200px; flex-shrink: 0;
  display: flex; flex-direction: column;
  border-right: 1px solid #2a2a3e;
  overflow: hidden;
}
.sidebar-right {
  width: 220px; flex-shrink: 0;
  border-left: 1px solid #2a2a3e;
  overflow: hidden;
}
.panel { border-bottom: 1px solid #2a2a3e; overflow: hidden; }
.layers-panel { height: 45%; }
.bottom-palette { flex: 1; overflow: auto; }
.status-bar {
  height: 22px; background: #0f0f1a; border-top: 1px solid #222;
  display: flex; align-items: center; padding: 0 12px;
  font-size: 11px; color: #666; flex-shrink: 0;
}
.dirty { color: #f5a623; }
.saved { color: #4CAF50; }
.no-world { color: #444; }
</style>
