/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */
// import { registerVueControllerComponents } from '@symfony/ux-vue';
// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';
import './bootstrap';

// registerVueControllerComponents(require.context('./vue/controllers', true, /\.vue$/));

import { createApp } from 'vue';
import App from './components/Demo.vue';
import SongSmallPreview from './components/SmallSongList.vue';

createApp(SongSmallPreview).mount('.list-songs');
