import Category from './Category.vue'
import Option from './Option.vue'
import Vue from '../../vendor/vue-custom'
import CrudMixin from './crud-mixin'
import draggable from 'vuedraggable'

document.addEventListener('DOMContentLoaded', function() {
  if ($('#taxonomy-tree').length > 0) {
    
    // register globally see https://vuejs.org/v2/guide/components-edge-cases.html#Recursive-Components
    Vue.component('Category', Category)
    Vue.component('Option', Option)
    Vue.component('draggable', draggable)

    new Vue({
      el: "#taxonomy-tree",
      mixins: [CrudMixin],
      components: { optioncomponent: Option },
      data: {
        taxonomy: null,
        deletedIds: []
      },      
      mounted() {
        $.getJSON('/api/taxonomy.json', (data) => this.taxonomy = data)
      },
      methods: {
        save() {
          let allItems = []
          for (let category of this.taxonomy) {
            this.recursiveSearchOptions(category, allItems)
          }
          $.post('/admin/taxonomy/save', {
              items: allItems.filter((item) => item.new || item.edited),
              deleted: this.deletedIds,
          }, function(result) {
              console.log("result post", result)
          })
        },
        recursiveSearchOptions(category, allItems) {
          allItems.push(category)
          for (let option of category.options) {
            allItems.push(option)
            if (option.subcategories) {
              for (let subcategory of option.subcategories) {
                this.recursiveSearchOptions(subcategory, allItems)
              }
            }
          }
          return allItems
        }
      }
    })
  }
})
