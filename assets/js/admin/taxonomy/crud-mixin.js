export default {
  methods: {
    generateId() { 
      return "NEW_" + Math.floor(Math.random() * 100000) 
    },
    createCategory() {
      return {
        id: this.generateId(),
        new: true,
        name: "Groupe de catégorie",
        options: [ this.createOption() ]
      }
    },
    createOption() {
      return { 
        id: this.generateId(),
        name: "Catégorie",
        new: true,
        subcategories: []
      }
    },
    updatePosition(array) {
      for(let i = 0; i < array.length; i++) {
        if (array[i].index != i) {
          array[i].index = i
          array[i].edited = true
        }
      }
    }
  }
}