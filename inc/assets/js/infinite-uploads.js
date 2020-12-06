jQuery(document).ready(function ($) {
  $('[data-toggle="tooltip"]').tooltip();

  var stopLoop = false;

  //show an error at top of main settings page
  var showError = function(error_message) {
    $('#iup-error').text(error_message.substr(0, 200)).show();
    $("html, body").animate({ scrollTop: 0 }, 1000);
  }

  var buildFilelist = function (remaining_dirs, nonce = '') {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    var data = {"remaining_dirs": remaining_dirs};
    if ( nonce ) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.scan;
    }
    $.post(ajaxurl + '?action=infinite-uploads-filelist', data, function (json) {
      if (json.success) {
        $('#iup-scan-storage').text(json.data.local_size);
        $('#iup-scan-files').text(json.data.local_files);
        if (!json.data.is_done) {
          buildFilelist(json.data.remaining_dirs, json.data.nonce);
        } else {
          location.reload();
          return true;
        }

      } else {
        showError(json.data);
        $('#scan-modal').modal('hide');
      }
    }, 'json').fail(function () {
      showError(iup_data.strings.ajax_error);
      $('#scan-modal').modal('hide');
    });
  };

  var fetchRemoteFilelist = function (next_token, nonce = '') {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    var data = {"next_token": next_token};
    if ( nonce ) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.scan;
    }
    $.post(ajaxurl + '?action=infinite-uploads-remote-filelist', data, function (json) {
      if (json.success) {
        $('#iup-scan-remote-storage').text(json.data.cloud_size);
        $('#iup-scan-remote-files').text(json.data.cloud_files);
        if (!json.data.is_done) {
          fetchRemoteFilelist(json.data.next_token, json.data.nonce);
        } else {
          //update values in next modal
          $('#iup-progress-size').text(json.data.remaining_size);
          $('#iup-progress-files').text(json.data.remaining_files);
          $('#iup-sync-progress-bar').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete).text(json.data.pcnt_complete + "%");

          $('#iup-sync-button').attr('data-target', '#upload-modal');
          $('#scan-remote-modal').modal('hide');
          $('#upload-modal').modal('show');
        }

      } else {
        showError(json.data);
        $('#scan-remote-modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('#scan-remote-modal').modal('hide');
      });
  };

  var syncFilelist = function (nonce = '') {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    var data = {};
    if ( nonce ) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.sync;
    }
    $.post(ajaxurl + '?action=infinite-uploads-sync', data, function (json) {
      if (json.success) {
        //$('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('#iup-progress-size').text(json.data.remaining_size);
        $('#iup-progress-files').text(json.data.remaining_files);
        $('#iup-sync-progress-bar').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete).text(json.data.pcnt_complete + "%");
        if (!json.data.is_done) {
          syncFilelist(json.data.nonce);
        } else {
          //update values in next modal
          $('#iup-enable-errors span').text(json.data.permanent_errors);
          if (json.data.permanent_errors) {
            $('#iup-enable-errors').show();
          }
          $('#iup-sync-button').attr('data-target', '#enable-modal');
          $('#upload-modal').modal('hide');
          $('#enable-modal').modal('show');
        }
        if (Array.isArray(json.data.errors) && json.data.errors.length) {
          $.each(json.data.errors, function (i, value) {
            $('#iup-sync-errors ul').append('<li><span class="dashicons dashicons-warning"></span> ' + value + '</li>');
          });
          $('#iup-sync-errors').show();
          var scroll = $("#iup-sync-errors")[0].scrollHeight;
          $("#iup-sync-errors").animate({scrollTop: scroll}, 5000);
        }

      } else {
        showError(json.data);
        $('#upload-modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('#upload-modal').modal('hide');
      });
  };

  var deleteFiles = function () {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    $.post(ajaxurl + '?action=infinite-uploads-delete', { 'nonce': iup_data.nonce.delete }, function (json) {
      if (json.success) {
        //$('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('#iup-delete-size').text(json.data.deletable_size);
        $('#iup-delete-files').text(json.data.deletable_files);
        if (!json.data.is_done) {
          deleteFiles();
        } else {
          location.reload();
          return true;
        }

      } else {
        showError(json.data);
        $('#delete-modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('#delete-modal').modal('hide');
      });
  };

  var downloadFiles = function (nonce = '') {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    var data = {};
    if ( nonce ) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.download;
    }
    $.post(ajaxurl + '?action=infinite-uploads-download', data, function (json) {
      if (json.success) {
        //$('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('#iup-download-size').text(json.data.deleted_size);
        $('#iup-download-files').text(json.data.deleted_files);
        $('#iup-download-progress-bar').css('width', json.data.pcnt_downloaded + "%").attr('aria-valuenow', json.data.pcnt_downloaded).text(json.data.pcnt_downloaded + "%");
        if (!json.data.is_done) {
          downloadFiles(json.data.nonce);
        } else {
          location.reload();
          return true;
        }
        if (Array.isArray(json.data.errors) && json.data.errors.length) {
          $.each(json.data.errors, function (i, value) {
            $('#iup-download-errors ul').append('<li><span class="dashicons dashicons-warning"></span> ' + value + '</li>');
          });
          $('#iup-download-errors').show();
          var scroll = $("#iup-download-errors")[0].scrollHeight;
          $("#iup-download-errors").animate({scrollTop: scroll}, 5000);
        }

      } else {
        showError(json.data);
        $('#download-modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('#download-modal').modal('hide');
      });
  };

  //Scan
  $('#scan-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    stopLoop = false;
    buildFilelist([]);
  }).on('hide.bs.modal', function () {
    stopLoop = true;
  })

  //Compare to live
  $('#scan-remote-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    stopLoop = false;
    fetchRemoteFilelist(null);
  }).on('hide.bs.modal', function () {
    stopLoop = true;
  })

  //Sync
  $('#upload-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    $('#iup-sync-errors').hide();
    $('#iup-sync-errors ul').empty();
    stopLoop = false;
    syncFilelist();
  }).on('hide.bs.modal', function () {
    stopLoop = true;
  })

  //Download
  $('#download-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    $('#iup-download-errors').hide();
    $('#iup-download-errors ul').empty();
    stopLoop = false;
    downloadFiles();
  }).on('hide.bs.modal', function () {
    stopLoop = true;
  })

  //Delete
  $('#delete-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    stopLoop = false;
    $('#iup-delete-local-button').show();
    $('#iup-delete-local-spinner').hide();
  }).on('hide.bs.modal', function () {
    stopLoop = true;
  })

  //Delete local files
  $('#iup-delete-local-button').on('click', function () {
    $(this).hide();
    $('#iup-delete-local-spinner').show();
    deleteFiles();
  });

  //Enable infinite uploads
  $('#iup-enable-button').on('click', function () {
    $('#iup-enable-button').hide();
    $('#iup-enable-spinner').removeClass('text-hide');
    $.post(ajaxurl + '?action=infinite-uploads-toggle', {'enabled': true, 'nonce': iup_data.nonce.toggle}, function (json) {
      if (json.success) {
        location.reload();
        return true;
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('#iup-enable-spinner').addClass('text-hide');
        $('#iup-enable-button').show();
        $('#enable-modal').modal('hide');
      });
  });

  //refresh api data
  $('.iup-refresh-icon .dashicons').on('click', function () {
    $(this).hide();
    $('.iup-refresh-icon .spinner-grow').removeClass('text-hide');
    window.location = $(this).attr('data-target');
  });

  //Charts
  var bandwidthFormat = function (bytes) {
    if (bytes < 1024) {
      return bytes + ' B';
    } else if (bytes < 1024 * 1024) {
      return Math.round(bytes / 1024) + ' KB';
    } else if (bytes < 1024 * 1024 * 1024) {
      return Math.round(bytes / 1024 / 1024 * 10) / 10 + ' MB';
    } else {
      return Math.round(bytes / 1024 / 1024 / 1024 * 100) / 100 + ' GB';
    }
  };

  var sizelabel = function (tooltipItem, data) {
    var label = ' ' + data.labels[tooltipItem.index] || '';
    return label;
  };

  window.onload = function () {
    var pie1 = document.getElementById('iup-local-pie');
    if (pie1) {

      var config_local = {
        type: 'pie',
        data: iup_data.local_types,
        options: {
          responsive: true,
          legend: false,
          tooltips: {
            callbacks: {
              label: sizelabel
            },
            backgroundColor: '#F1F1F1',
            bodyFontColor: '#2A2A2A',
          },
          title: {
            display: true,
            position: 'bottom',
            fontSize: 18,
            fontStyle: 'normal',
            text: iup_data.local_types.total
          }
        }
      };

      var ctx = pie1.getContext('2d');
      window.myPieLocal = new Chart(ctx, config_local);
    }

    var pie2 = document.getElementById('iup-cloud-pie');
    if (pie2) {

      var config_cloud = {
        type: 'pie',
        data: iup_data.cloud_types,
        options: {
          responsive: true,
          legend: false,
          tooltips: {
            callbacks: {
              label: sizelabel
            },
            backgroundColor: '#F1F1F1',
            bodyFontColor: '#2A2A2A',
          },
          title: {
            display: true,
            position: 'bottom',
            fontSize: 18,
            fontStyle: 'normal',
            text: iup_data.cloud_types.total
          }
        }
      };

      var ctx = pie2.getContext('2d');
      window.myPieCloud = new Chart(ctx, config_cloud);
    }
  };
});
