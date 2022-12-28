import {render} from '@wordpress/element';
import {Component} from '@wordpress/element';
import Library from './index';
import 'bootstrap/dist/css/bootstrap.min.css';
import '../../assets/css/admin.css';

class InfiniteUploads extends Component {
	render() {
		return <Library/>;
	}
}

document.addEventListener('DOMContentLoaded', function (event) {
	render(<InfiniteUploads/>, document.getElementById('iup-videos-page'));
});
