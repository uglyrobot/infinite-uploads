/**
 * External Dependencies
 */
const path = require('path');

/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config.js');

module.exports = {
  ...defaultConfig,
  ...{
    entry: {
      block: path.resolve(process.cwd(), 'inc/video/block/src', 'index.js'),
      settings: path.resolve(process.cwd(), 'inc/video/settings', 'settings.js'),
    },

  },
};
