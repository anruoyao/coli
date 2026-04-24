import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useMarketStore = defineStore('marketplace', {
    state: () => {
        return {
            categories: [],
			products: [],
			filter: {},
			product: null,
			metadata: {},
			bookmarkedProducts: [],
        };
    },
	getters: {
		activeFiltersCount: function() {
			let count = 0;

			if(! Object.keys(this.filter).length) {
				return count;
			}

			if(this.filter.query) {
				count++;
			}

			if(this.filter.category_id) {
				count++;
			}

			if(this.filter.price.from || this.filter.price.to) {
				count++;
			}

			if(this.filter.is_store) {
				count++;
			}

			if(this.filter.with_discount) {
				count++;
			}

			if(this.filter.high_rating) {
				count++;
			}

			let currencies = Object.keys(this.filter.currencies).find((key) => {
				return this.filter.currencies[key] === true;
			});

			if(currencies) {
				count++;
			}

			let conditions = Object.keys(this.filter.conditions).find((key) => {
				return this.filter.conditions[key] === true;
			});

			if(conditions) {
				count++;
			}

			let types = Object.keys(this.filter.types).find((key) => {
				return this.filter.types[key] === true;
			});

			if(types) {
				count++;
			}

			return count;
		}	
	},
    actions: {
		getLastProductId: function() {
			if(this.products.length) {
				return this.products[this.products.length -1].id;
			}

			return null;
		},
		makeLoadRequest: async function () {
			return await colibriAPI().marketplace().with({
				filter: this.filter
			}).sendTo('products');
		},
		fetchProducts: async function() {
			await this.makeLoadRequest().then((response) => {
				this.products = response.data.data;
			}).catch((error) => {
				this.products = [];
			});
		},
		fetchBookmarkedProducts: async function() {
			await colibriAPI().marketplace().getFrom('bookmarks').then((response) => {
				this.bookmarkedProducts = response.data.data;
			}).catch((error) => {
				this.bookmarkedProducts = [];
			});
		},
		loadMoreProducts: async function() {
			return await this.makeLoadRequest().then((response) => {
				let products = response.data.data;
				
				if (products.length) {
					this.products = this.products.concat(products);

					return true;
				}

				return false;
			}).catch((error) => {
				return false;
			});
		},
		fetchProduct: async function(id) {
			if(this.product && this.product.id == id) {
				return false;
			}

			await colibriAPI().marketplace().getFrom(`products/${id}`).then((response) => {
				this.product = response.data.data;
			}).catch((error) => {});
		},
		fetchCategories: async function() {
			if(! this.categories.length) {
				await colibriAPI().marketplace().getFrom('categories').then((response) => {
					this.categories = response.data.data;
				}).catch((error) => {
					if(error.response) {
						console.error(error.response.data.message);
					}
				});
			}
		},
		bookmarkProduct: async function(id) {
			return await colibriAPI().marketplace().with({
				id: id
			}).sendTo('bookmarks/add');
		},
		fetchMetadata: async function() {
			if(Object.keys(this.metadata).length) {
				return false;
			}
			else{
				this.filter = this.getFilterSchema();

				await colibriAPI().marketplace().getFrom('metadata').then((response) => {
					this.metadata = response.data.data;
				}).catch((error) => {});
			}
		},
		resetFilter: function() {
			this.filter = this.getFilterSchema();
		},
		getFilterSchema: function() {
			return {
				query: '',
				category_id: null,
				price: {
					from: '',
					to: ''
				},
				is_store: false,
				with_discount: false,
				high_rating: false,
				conditions: {},
				currencies: {},
				types: {},
				cursor: null
			};
		}
    }
});

export { useMarketStore };