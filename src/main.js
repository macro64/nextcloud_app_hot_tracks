import Vue from 'vue'
import App from './App.vue'
import './style.css'

const root = document.getElementById('strava-root')
if (root) {
	new Vue({
		render: (h) => h(App),
	}).$mount(root)
}
