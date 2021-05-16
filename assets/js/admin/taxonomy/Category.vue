<template>
  <div class="category-item tree-node">

    <span class="category-item" @click="displayChildren = !displayChildren">
      <span class="name" :class="{expandable: expandable}"
            :style="(!category.displayInForm || !category.displayInMenu) ? 'opacity: 0.5' : ''">
        (Groupe) {{ category.name }}
      </span>
      <span style="opacity: .6;margin-right: 10px;">( {{ category.customString || category.id }} )</span>
      <span v-if="!category.displayInForm || !category.displayInMenu" class="fa fa-eye-slash"></span>
      <span v-if="expandable" class="arrow-after"></span>
      <span v-if="category.isMandatory" class="label label-warning">{{ t('js.taxonomy.mandatory') }}</span>
      <span class="label label-default">{{ category.singleOption ?   t('js.taxonomy.unique') :   t('js.taxonomy.multiple') }}</span>
      <span v-if="category.enableDescription" class="label label-info">{{ t('js.taxonomy.categoriesDescription') }}</span>
    </span>
    <span class="actions">
      <a href="#" class="btn btn-sm btn-default"> <i class="fa fa-pen" aria-hidden="true"></i></a>
      <a href="#" class="btn btn-sm btn-default"> <i class="fa fa-trash" aria-hidden="true"></i></a>
    </span>

    <div v-show="displayChildren" class="children category-children options-container" 
         :class="{ expanded: category.showExpanded }">
      <draggable v-model="category.options" group="options" @end="updatePosition(category.options)">
        <Option v-for="option in category.options" :key="option.id" :option="option"></Option>
      </draggable>
      <div class="new-item option" @click="addOption">{{ t('js.taxonomy.addCategory') }}</div>
    </div>
    
  </div>
</template>

<script>
import CrudMixin from './crud-mixin'

export default {
  props: ['category'],
  mixins: [ CrudMixin ],
  data() {
    return {
      displayChildren: false,
      myArray: [{id: 1, name: "toto"}, {id: 2, name: "Foo"}]
    }
  },
  computed: {
    expandable() {
      return this.category.options && this.category.options.length > 0
    }
  },
  mounted() {
    this.category.type = 'category'
    if (this.category.new) this.displayChildren = true
  },
  methods: {
    addOption() {
      this.category.options.push(this.createOption())
    }
  }
}
</script>

<style>
  .children {
    padding-left: 1rem;
  }
  .name { 
    margin-right: 10px; 
    }

  span.category-item .name
  {
    font-weight: bold;
    text-transform: uppercase;
    font-size: .85em;
  }

  span.category-item .arrow-after { 
    margin-right: 10px; 
  }

  .expandable:hover {
    cursor: pointer;
    color: black;
  }

  .arrow-after {
    display: inline-block;
    margin-left: 5px;
    border: solid 3px;
    margin-bottom: -1px;
    border-color: #545454 transparent transparent transparent;
  }
  .actions {
    margin-left: 1rem;
  }
  .btn-sm.btn-default {
    border: none;
    padding: 5px 5px;
    font-size: 10px;
    color: grey;
  }
</style>