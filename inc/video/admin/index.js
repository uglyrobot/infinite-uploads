import {render} from '@wordpress/element';
import {Component} from '@wordpress/element';
import Library from './components/Library';
import Settings from "./components/Settings";
import 'bootstrap/dist/css/bootstrap.min.css';
import '../../assets/css/admin.css';

class InfiniteUploadsLibrary extends Component {
	render() {
		return <Library/>;
	}
}

class InfiniteUploadsSettings extends Component {
	render() {
		return <Settings/>;
	}
}

document.addEventListener('DOMContentLoaded', function (event) {
	const library = document.getElementById("iup-videos-page")
	if (library) {
		render(<InfiniteUploadsLibrary/>, library);
	}
	const settings = document.getElementById("iup-video-settings-page")
	if (settings) {
		render(<InfiniteUploadsSettings/>, settings);
	}
});