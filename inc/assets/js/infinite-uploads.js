jQuery(document).ready(function ($) {
  $('[data-toggle="tooltip"]').tooltip();

  var stopLoop = false;

  var buildFilelist = function (remaining_dirs) {
    if (stopLoop) {
      return false;
    }

    var data = {"remaining_dirs": remaining_dirs};
    $.post(ajaxurl + '?action=infinite-uploads-filelist', data, function (json) {
      if (json.success) {
        $('.iup-scan-storage').text(json.data.local_size);
        $('.iup-scan-files').text(json.data.local_files);
        if (!json.data.is_done) {
          buildFilelist(json.data.remaining_dirs);
        } else {
          location.reload();
          return true;
        }

      } else {
        $('#iup-error').text(json.data.substr(0, 200));
        $('#iup-error').show();
      }
    }, 'json').fail(function () {
      $('#iup-error').text("Unknown Error");
      $('#iup-error').show();
    });
  };

  var fetchRemoteFilelist = function (next_token) {

    //progress indication
    $('.iup-scan-progress').show();
    $('.iup-scan-progress .spinner-border').addClass('text-hide');
    $('.iup-scan-progress .iup-cloud .spinner-border').removeClass('text-hide');
    $('.iup-scan-progress h3').addClass('text-muted');
    $('.iup-scan-progress .iup-cloud h3').removeClass('text-muted');

    var data = {"next_token": next_token};
    $.post(ajaxurl + '?action=infinite-uploads-remote-filelist', data, function (json) {
      if (json.success) {
        $('.iup-progress-gauges-cloud, .iup-sync-progress-bar .iup-local div').show();
        $('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('.iup-progress-size').text(json.data.remaining_size);
        $('.iup-progress-files').text(json.data.remaining_files);
        $('#iup-sync-progress-bar').show();
        $('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
        $('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
        if (!json.data.is_done) {
          fetchRemoteFilelist(json.data.next_token);
        } else {
          syncFilelist();
        }

      } else {
        $('#iup-error').text(json.data.substr(0, 200));
        $('#iup-error').show();

        $('.iup-scan-progress').hide();
        $('#iup-sync').show();
      }
    }, 'json')
      .fail(function () {
        $('#iup-error').text("Unknown Error");
        $('#iup-error').show();

        $('.iup-scan-progress').hide();
        $('#iup-sync').show();
      });
  };

  var syncFilelist = function () {

    //progress indication
    $('.iup-scan-progress').show();
    $('.iup-scan-progress .spinner-border').addClass('text-hide');
    $('.iup-scan-progress .iup-sync .spinner-border').removeClass('text-hide');
    $('.iup-scan-progress h3').addClass('text-muted');
    $('.iup-scan-progress .iup-sync h3').removeClass('text-muted');
    $('#iup-sync-progress-bar .progress-bar').addClass('progress-bar-animated progress-bar-striped');

    $.post(ajaxurl + '?action=infinite-uploads-sync', {}, function (json) {
      if (json.success) {
        $('.iup-progress-pcnt').text(json.data.pcnt_complete);
        $('.iup-progress-size').text(json.data.remaining_size);
        $('.iup-progress-files').text(json.data.remaining_files);
        $('#iup-sync-progress-bar .iup-cloud').css('width', json.data.pcnt_complete + "%").attr('aria-valuenow', json.data.pcnt_complete);
        $('#iup-sync-progress-bar .iup-local').css('width', 100 - json.data.pcnt_complete + "%").attr('aria-valuenow', 100 - json.data.pcnt_complete);
        if (!json.data.is_done) {
          syncFilelist();
        } else {
          $('#iup-continue-sync').show();
          $('.iup-scan-progress').hide();
          $('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
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

        $('#iup-continue-sync').show();
        $('.iup-scan-progress').hide();
        $('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
      }
    }, 'json')
      .fail(function () {
        $('#iup-error').text("Unknown Error");
        $('#iup-error').show();

        $('#iup-continue-sync').show();
        $('.iup-scan-progress').hide();
        $('#iup-sync-progress-bar .progress-bar').removeClass('progress-bar-animated progress-bar-striped');
      });
  };

  $('#scan-modal').on('shown.bs.modal', function () {
    buildFilelist([]);
  }).on('hidden.bs.modal', function () {
    stopLoop = true;
  })


  //Syncing
  $('#iup-sync').on('click', function () {
    $('#iup-sync, #iup-continue-sync, #iup-error').hide();

    buildFilelist([]);
  });
  //Resync in case of error
  $('#iup-continue-sync').on('click', function () {
    $('#iup-sync, #iup-continue-sync, #iup-error').hide();

    syncFilelist();
  });
  //Enable infinite uploads
  $('#iup-enable').on('click', function () {
    $('#iup-enable-spinner').removeClass('text-hide');
    $.post(ajaxurl + '?action=infinite-uploads-toggle', {'enabled': true}, function (json) {
      if (json.success) {
        $('#iup-enable').hide();
        $('#iup-enable-spinner').addClass('text-hide');
      }
    }, 'json')
      .fail(function () {
        $('#iup-error').text("Unknown Error");
        $('#iup-error').show();
        $('#iup-enable-spinner').addClass('text-hide');
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

  window.onload = function () {
    var ctx = document.getElementById('iup-local-pie').getContext('2d');
    window.myPieLocal = new Chart(ctx, config_local);

    var ctx = document.getElementById('iup-cloud-pie').getContext('2d');
    window.myPieCloud = new Chart(ctx, config_cloud);
  };
});
