jQuery(document).ready(function ($) {
  $('[data-toggle="tooltip"]').tooltip();

  var iupStopLoop = false;
  var iupProcessingLoop = false;
  var iupLoopErrors = 0;
  var iupAjaxCall = false;

  //show a confirmation warning if leaving page during a bulk action
  $(window).bind('beforeunload', function () {
    if (iupProcessingLoop) {
      return iup_data.strings.leave_confirmation;
    }
  });

  //show an error at top of main settings page
  var showError = function (error_message) {
    $('#iup-error').text(error_message.substr(0, 200)).show();
    $("html, body").animate({scrollTop: 0}, 1000);
  }

  var buildFilelist = function (remaining_dirs, nonce = '') {
    if (iupStopLoop) {
      iupStopLoop = false;
      iupProcessingLoop = false;
      return false;
    }
    iupProcessingLoop = true;

    var data = {"remaining_dirs": remaining_dirs};
    if (nonce) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.scan;
    }
    $.post(ajaxurl + '?action=infinite-uploads-filelist', data, function (json) {
      if (json.success) {
        $('#iup-scan-storage').text(json.data.local_size);
        $('#iup-scan-files').text(json.data.local_files);
        $('#iup-scan-progress').show();
        if (!json.data.is_done) {
          buildFilelist(json.data.remaining_dirs, json.data.nonce);
        } else {
          iupProcessingLoop = false;
          location.reload();
          return true;
        }

      } else {
        showError(json.data);
        $('.modal').modal('hide');
      }
    }, 'json').fail(function () {
      showError(iup_data.strings.ajax_error);
      $('.modal').modal('hide');
    });
  };

  var fetchRemoteFilelist = function (next_token, nonce = '') {
    if (iupStopLoop) {
      iupStopLoop = false;
      iupProcessingLoop = false;
      return false;
    }
    iupProcessingLoop = true;

    var data = {"next_token": next_token};
    if (nonce) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.scan;
    }
    $.post(ajaxurl + '?action=infinite-uploads-remote-filelist', data, function (json) {
      if (json.success) {
        $('#iup-scan-remote-storage').text(json.data.cloud_size);
        $('#iup-scan-remote-files').text(json.data.cloud_files);
        $('#iup-scan-remote-progress').show();
        if (!json.data.is_done) {
          fetchRemoteFilelist(json.data.next_token, json.data.nonce);
        } else {
          if ('upload' === window.iupNextStep) {
            //update values in next modal
            $('#iup-progress-size').text(json.data.remaining_size);
            $('#iup-progress-files').text(json.data.remaining_files);
            if ('0' == json.data.remaining_files) {
              $('#iup-upload-progress').hide();
            } else {
              $('#iup-upload-progress').show();
            }
            $('#iup-sync-progress-bar').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete).text(json.data.pcnt_complete + "%");

            $('#iup-sync-button').attr('data-target', '#upload-modal');
            $('.modal').modal('hide');
            $('#upload-modal').modal('show');
          } else if ('download' === window.iupNextStep) {
            $('.modal').modal('hide');
            $('#download-modal').modal('show');
          } else {
            location.reload();
          }
        }

      } else {
        showError(json.data);
        $('.modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('.modal').modal('hide');
      });
  };

  var syncFilelist = function (nonce = '') {
    if (iupStopLoop) {
      iupStopLoop = false;
      iupProcessingLoop = false;
      return false;
    }
    iupProcessingLoop = true;

    var data = {};
    if (nonce) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.sync;
    }
    iupAjaxCall = $.post(ajaxurl + '?action=infinite-uploads-sync', data, function (json) {
      iupLoopErrors = 0;
      if (json.success) {
        //$('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('#iup-progress-size').text(json.data.remaining_size);
        $('#iup-progress-files').text(json.data.remaining_files);
        $('#iup-upload-progress').show();
        $('#iup-sync-progress-bar').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete).text(json.data.pcnt_complete + "%");
        if (!json.data.is_done) {
          data.nonce = json.data.nonce; //save for future errors
          syncFilelist(json.data.nonce);
        } else {
          iupStopLoop = true;
          $('#iup-upload-progress').hide();
          //update values in next modal
          $('#iup-enable-errors span').text(json.data.permanent_errors);
          if (json.data.permanent_errors) {
            $('.iup-enable-errors').show();
          }
          $('#iup-sync-button').attr('data-target', '#enable-modal');
          $('.modal').modal('hide');
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
        $('.modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        //if we get an error like 504 try up to 6 times with an exponential backoff to let the server cool down before giving up.
        iupLoopErrors++;
        if (iupLoopErrors > 6) {
          showError(iup_data.strings.ajax_error);
          $('.modal').modal('hide');
          iupLoopErrors = 0;
          iupProcessingLoop = false;
        } else {
          var exponentialBackoff = Math.floor(Math.pow(iupLoopErrors, 2.5) * 1000); //max 90s
          console.log("Server error. Waiting " + exponentialBackoff + "ms before retrying");
          setTimeout(function () {
            syncFilelist(data.nonce);
          }, exponentialBackoff);
        }
      });
  };

  var getSyncStatus = function () {
    if (!iupProcessingLoop) {
      return false;
    }

    $.get(ajaxurl + '?action=infinite-uploads-status', function (json) {
      if (json.success) {
        $('#iup-progress-size').text(json.data.remaining_size);
        $('#iup-progress-files').text(json.data.remaining_files);
        $('#iup-upload-progress').show();
        $('#iup-sync-progress-bar').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete).text(json.data.pcnt_complete + "%");
      } else {
        showError(json.data);
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
      })
      .always(function () {
        setTimeout(function () {
          getSyncStatus();
        }, 15000);
      });
  };

  var deleteFiles = function () {
    if (iupStopLoop) {
      iupStopLoop = false;
      return false;
    }

    $.post(ajaxurl + '?action=infinite-uploads-delete', {'nonce': iup_data.nonce.delete}, function (json) {
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
        $('.modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('.modal').modal('hide');
      });
  };

  var downloadFiles = function (nonce = '') {
    if (iupStopLoop) {
      iupStopLoop = false;
      iupProcessingLoop = false;
      return false;
    }
    iupProcessingLoop = true;

    var data = {};
    if (nonce) {
      data.nonce = nonce;
    } else {
      data.nonce = iup_data.nonce.download;
    }
    $.post(ajaxurl + '?action=infinite-uploads-download', data, function (json) {
      iupLoopErrors = 0;
      if (json.success) {
        //$('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('#iup-download-size').text(json.data.deleted_size);
        $('#iup-download-files').text(json.data.deleted_files);
        $('#iup-download-progress').show();
        $('#iup-download-progress-bar').css('width', json.data.pcnt_downloaded + "%").attr('aria-valuenow', json.data.pcnt_downloaded).text(json.data.pcnt_downloaded + "%");
        if (!json.data.is_done) {
          data.nonce = json.data.nonce; //save for future errors
          downloadFiles(json.data.nonce);
        } else {
          iupProcessingLoop = false;
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
        $('.modal').modal('hide');
      }
    }, 'json')
      .fail(function () {
        //if we get an error like 504 try up to 6 times before giving up.
        iupLoopErrors++;
        if (iupLoopErrors > 6) {
          showError(iup_data.strings.ajax_error);
          $('.modal').modal('hide');
          iupLoopErrors = 0;
          iupProcessingLoop = false;
        } else {
          var exponentialBackoff = Math.floor(Math.pow(iupLoopErrors, 2.5) * 1000); //max 90s
          console.log("Server error. Waiting " + exponentialBackoff + "ms before retrying");
          setTimeout(function () {
            downloadFiles(data.nonce);
          }, exponentialBackoff);
        }
      });
  };

  //Scan
  $('#scan-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    iupStopLoop = false;
    buildFilelist([]);
  }).on('hide.bs.modal', function () {
    iupStopLoop = true;
    iupProcessingLoop = false;
  })

  //Compare to live
  $('#scan-remote-modal').on('show.bs.modal', function (e) {
    $('#iup-error').hide();
    iupStopLoop = false;
    var button = $(e.relatedTarget) // Button that triggered the modal
    window.iupNextStep = button.data('next') // Extract info from data-* attributes
    fetchRemoteFilelist(null);
  }).on('hide.bs.modal', function () {
    iupStopLoop = true;
    iupProcessingLoop = false;
  })

  //Sync
  $('#upload-modal').on('show.bs.modal', function () {
    $('.iup-enable-errors').hide(); //hide errors on enable modal
    $('#iup-collapse-errors').collapse('hide');
    $('#iup-error').hide();
    $('#iup-sync-errors').hide();
    $('#iup-sync-errors ul').empty();
    iupStopLoop = false;
    syncFilelist();
    setTimeout(function () {
      getSyncStatus();
    }, 15000);
  }).on('shown.bs.modal', function () {
    $('#scan-remote-modal').modal('hide');
  }).on('hide.bs.modal', function () {
    iupStopLoop = true;
    iupProcessingLoop = false;
    iupAjaxCall.abort();
  })

  //Make sure upload modal closes
  $('#enable-modal').on('shown.bs.modal', function () {
    $('#upload-modal').modal('hide');
  }).on('hidden.bs.modal', function () {
    $('#iup-enable-spinner').addClass('text-hide');
    $('#iup-enable-button').show();
  })

  $('#iup-collapse-errors').on('show.bs.collapse', function () {
    // load up list of errors via ajax
    $.get(ajaxurl + '?action=infinite-uploads-sync-errors', function (json) {
      if (json.success) {
        $('#iup-collapse-errors .list-group').html(json.data);
      }
    }, 'json');
  })

  $('#iup-resync-button').on('click', function (e) {
    $('.iup-enable-errors').hide(); //hide errors on enable modal
    $('#iup-collapse-errors').collapse('hide');
    $('#iup-enable-button').hide();
    $('#iup-enable-spinner').removeClass('text-hide');
    $.post(ajaxurl + '?action=infinite-uploads-reset-errors', {foo: "bar"}, function (json) {
      if (json.success) {
        $('.modal').modal('hide');
        $('#upload-modal').modal('show');
        return true;
      }
    }, 'json')
      .fail(function () {
        showError(iup_data.strings.ajax_error);
        $('.modal').modal('hide');
      });
  })

  //Download
  $('#download-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    $('#iup-download-errors').hide();
    $('#iup-download-errors ul').empty();
    iupStopLoop = false;
    downloadFiles();
  }).on('hide.bs.modal', function () {
    iupStopLoop = true;
    iupProcessingLoop = false;
  })

  //Delete
  $('#delete-modal').on('show.bs.modal', function () {
    $('#iup-error').hide();
    iupStopLoop = false;
    $('#iup-delete-local-button').show();
    $('#iup-delete-local-spinner').hide();
  }).on('hide.bs.modal', function () {
    iupStopLoop = true;
  })

  //Delete local files
  $('#iup-delete-local-button').on('click', function () {
    $(this).hide();
    $('#iup-delete-local-spinner').show();
    deleteFiles();
  });

  //Enable infinite uploads
  $('#iup-enable-button').on('click', function () {
    $('.iup-enable-errors').hide(); //hide errors on enable modal
    $('#iup-collapse-errors').collapse('hide');
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
        $('.modal').modal('hide');
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
