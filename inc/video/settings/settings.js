import {render} from '@wordpress/element';
import {Component} from '@wordpress/element';
import Library from './index';

class InfiniteUploads extends Component {

  render() {
    return (
	    <Library/>
    );
  }
}

document.addEventListener("DOMContentLoaded", function (event) {
  render(
    <InfiniteUploads/>,
    document.getElementById('iup-videos-page')
  )
});
