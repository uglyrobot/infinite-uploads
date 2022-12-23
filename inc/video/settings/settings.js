import {render} from '@wordpress/element';
import {Component} from '@wordpress/element';
import Page from './index';
import 'bootstrap/dist/css/bootstrap.min.css';
import '../../assets/css/admin.css';

class InfiniteUploads extends Component {

  render() {
    return (
      <Page/>
    );
  }
}

document.addEventListener("DOMContentLoaded", function (event) {
  render(
    <InfiniteUploads/>,
    document.getElementById('iup-settings-page')
  )
});
