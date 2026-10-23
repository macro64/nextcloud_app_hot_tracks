import Vue from 'vue'
import App from './App.vue'
import './style.css'

function mount() {
	const root = document.getElementById('hot-tracks-root')
	if (!root || root.dataset.hotTracksMounted === '1') {
		return Boolean(root)
	}
	root.dataset.hotTracksMounted = '1'
	new Vue({
		render: (h) => h(App),
	}).$mount(root)
	return true
}

if (!mount()) {
	const observer = new MutationObserver(() => {
		if (mount()) observer.disconnect()
	})
	observer.observe(document.documentElement, { childList: true, subtree: true })
}
