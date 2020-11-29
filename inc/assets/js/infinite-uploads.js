jQuery(document).ready(function ($) {
  $('[data-toggle="tooltip"]').tooltip();

  var stopLoop = false;

  var buildFilelist = function (remaining_dirs) {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    var data = {"remaining_dirs": remaining_dirs};
    $.post(ajaxurl + '?action=infinite-uploads-filelist', data, function (json) {
      if (json.success) {
        $('#iup-scan-storage').text(json.data.local_size);
        $('#iup-scan-files').text(json.data.local_files);
        if (!json.data.is_done) {
          buildFilelist(json.data.remaining_dirs);
        } else {
          location.reload();
          return true;
        }

      } else {
        $('#scan-modal').modal('hide');
        $('#iup-error').text(json.data.substr(0, 200));
        $('#iup-error').show();
      }
    }, 'json').fail(function () {
      $('#scan-modal').modal('hide');
      $('#iup-error').text("Unknown Error");
      $('#iup-error').show();
    });
  };

  var fetchRemoteFilelist = function (next_token) {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    var data = {"next_token": next_token};
    $.post(ajaxurl + '?action=infinite-uploads-remote-filelist', data, function (json) {
      if (json.success) {
        $('#iup-scan-remote-storage').text(json.data.cloud_size);
        $('#iup-scan-remote-files').text(json.data.cloud_files);
        if (!json.data.is_done) {
          fetchRemoteFilelist(json.data.next_token);
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
        $('#scan-remote-modal').modal('hide');
        $('#iup-error').text(json.data.substr(0, 200));
        $('#iup-error').show();
      }
    }, 'json')
      .fail(function () {
        $('#scan-remote-modal').modal('hide');
        $('#iup-error').text("Unknown Error");
        $('#iup-error').show();
      });
  };

  var syncFilelist = function () {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    $.post(ajaxurl + '?action=infinite-uploads-sync', {}, function (json) {
      if (json.success) {
        //$('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('#iup-progress-size').text(json.data.remaining_size);
        $('#iup-progress-files').text(json.data.remaining_files);
        $('#iup-sync-progress-bar').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete).text(json.data.pcnt_complete + "%");
        if (!json.data.is_done) {
          syncFilelist();
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
        $('#upload-modal').modal('hide');
        $('#iup-error').text(json.data.substr(0, 200));
        $('#iup-error').show();
      }
    }, 'json')
      .fail(function () {
        $('#upload-modal').modal('hide');
        $('#iup-error').text("Unknown Error");
        $('#iup-error').show();
      });
  };

  var deleteFiles = function () {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    $.post(ajaxurl + '?action=infinite-uploads-delete', {}, function (json) {
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
        if (Array.isArray(json.data.errors) && json.data.errors.length) {
          $('#iup-error').html('<ul>');
          $.each(json.data.errors, function (i, value) {
            $('#iup-error').append('<li>' + value + '</li>');
          });
          $('#iup-error').append('</ul>');
          $('#iup-error').show();
        } else {
          $('#iup-error').hide();
        }

      } else {
        $('#iup-error').text(json.data.substr(0, 200));
        $('#iup-error').show();
      }
    }, 'json')
      .fail(function () {
        $('#iup-error').text("Unknown Error");
        $('#iup-error').show();
      });
  };

  var downloadFiles = function () {
    if (stopLoop) {
      stopLoop = false;
      return false;
    }

    $.post(ajaxurl + '?action=infinite-uploads-download', {}, function (json) {
      if (json.success) {
        //$('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('#iup-download-size').text(json.data.deleted_size);
        $('#iup-download-files').text(json.data.deleted_files);
        $('#iup-download-progress-bar').css('width', json.data.pcnt_downloaded + "%").attr('aria-valuenow', json.data.pcnt_downloaded).text(json.data.pcnt_downloaded + "%");
        if (!json.data.is_done) {
          downloadFiles();
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
        $('#iup-error').text(json.data.substr(0, 200));
        $('#iup-error').show();
      }
    }, 'json')
      .fail(function () {
        $('#iup-error').text("Unknown Error");
        $('#iup-error').show();
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
    $.post(ajaxurl + '?action=infinite-uploads-toggle', {'enabled': true}, function (json) {
      if (json.success) {
        location.reload();
        return true;
      }
    }, 'json')
      .fail(function () {
        $('#iup-error').text("Unknown Ajax Error");
        $('#iup-error').show();
        $('#iup-enable-spinner').addClass('text-hide');
        $('#iup-enable-button').show();
        $('#enable-modal').modal('hide');
      });
  });

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
  //Charts
  var config_local = {
    type: 'pie',
    data: local_types,
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
        text: local_types.total
      }
    }
  };

  var config_cloud = {
    type: 'pie',
    data: cloud_types,
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
        text: cloud_types.total
      }
    }
  };

  window.onload = function () {
    var pie1 = document.getElementById('iup-local-pie');
    if (pie1) {
      var ctx = pie1.getContext('2d');
      window.myPieLocal = new Chart(ctx, config_local);
    }

    var pie2 = document.getElementById('iup-cloud-pie');
    if (pie2) {
      var ctx = pie2.getContext('2d');
      window.myPieCloud = new Chart(ctx, config_cloud);
    }
  };
});
