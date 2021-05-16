<template>
  <div class="option tree-node">
    <div v-if="option.name"> <!-- Root fake option does not have name -->
      <span class="option-item" :class="{expandable: expandable}" @click="displayChildren = !displayChildren"
            :style="(!option.displayInForm || !option.displayInMenu) ? 'opacity: 0.5' : ''">
        <span class="icon" :class="option.icon" :style="{color: option.color}"></span>
        <span class="name">{{ option.name }}</span>
        <span v-if="!option.displayInForm || !option.displayInMenu" class="fa fa-eye-slash"></span>
        <span style="opacity: .6;">( {{ option.customId || option.id }} )</span>
        <!-- <span style="opacity: .6;"> {{ option.osmTagsStringified }} </span> -->
        <span v-if="expandable" class="arrow-after"></span>
      </span>
      <span class="actions">
        <a href="#" class="btn btn-sm btn-default"> <i class="fa fa-pen" aria-hidden="true"></i></a>
        <a href="#" class="btn btn-sm btn-default"> <i class="fa fa-trash" aria-hidden="true"></i></a>
      </span>
    </div>

    <div v-show="!option.name || displayChildren" class="children categories-container">
      <draggable v-model="option.subcategories" group="categories" @end="updatePosition(options.subcategories)">
        <Category v-for="subcategory in option.subcategories" :key="subcategory.id" :category="subcategory"></Category>
      </draggable>      
      <div class="new-item category" @click="addCategory">{{ t('js.taxonomy.addCategoryGroup') }}</div>
    </div>
  </div>
</template>

<script>
import CrudMixin from './crud-mixin'

export default {
  props: ['option'],
  mixins: [CrudMixin],
  data() {
    return {
      displayChildren: false
    }
  },
  methods: {
    addCategory() {
      this.option.subcategories.push(this.createCategory())
    }
  },
  computed: {
    expandable() {
      return this.option.subcategories && this.option.subcategories.length > 0
    }
  }
}
</script>

<style>

</style>