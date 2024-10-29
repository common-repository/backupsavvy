var $j = jQuery.noConflict();

$j(document).ready(function ($) {
  BcsvvyVars = {
    progress_interval: null
  };

  BCSVVYadditional = {
    num_sites: 50,
    page: 1,
    name: '',
    num_sites_rep: 50,
    page_rep: 1,
    option: 'sites',
    start: 0,
    load_sites: function () {
      $.post(ajaxurl, {
        action: "backupsavvy_load_sites",
        method: 'POST',
        nonce: localVars.nonce,
        number: this.num_sites,
        page: this.page,
      }, function (response_) {
        var response = $.parseJSON(response_);
        var container = '.container.sites-list';
        $(container).find('.spinner').css('display', 'none');
        if(response.sites) {
          $('table#sites-list tbody').html('').append(response.sites);
          // creating input pull
          $('table#sites-list').find('tr').each(function (e) {
            if ($(this).find('td.choice input[type="checkbox"]').prop('checked') === true) {
              create_inputs($(this).find('td.choice input'));
            }
            $(this).find('td.choice input').click(function () {
              create_inputs(this);
            });
          });
        }
        if(response.pager.length > 1) {
          $('.tablenav').removeClass('hidden');
          $(container).find('.tablenav-pages').html('').append(response.pager);
        } else {
          $('.tablenav').addClass('hidden');
          $('.tablenav-pages').html('')
        }
      })
    },
    load_report_list: function () {
      $.post(ajaxurl, {
        action: "backupsavvy_load_report_list",
        method: 'POST',
        nonce: localVars.nonce,
        number: this.num_sites_rep,
        page: this.page_rep,
      }, function (response_) {
        var response = $.parseJSON(response_);
        var container = '.container.existing-list';
        $(container).find('.spinner').css('display', 'none');
        $('table#existing-backups').append(response.tbody);
        if(response.pager.length > 1) {
          $('.tablenav').removeClass('hidden');
          $(container).find('.tablenav-pages').html('').append(response.pager);
        } else {
          $('.tablenav').addClass('hidden');
          $('.tablenav-pages').html('')
        }
      })
    },
    import: function() {
      if(this.start === 0) {
        var param = {
          key: 'start',
          title: 'Import progress',
          text: 'Import process is started. It can takes some time, please wait'
        };
        bcsvvNamespace.progress_log(false, param);
      }
      var obj = this;
      $.post(ajaxurl, {
        action: "backupsavvy_import_mainwp",
        method: 'POST',
        start: obj.start,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        if(response.next !== 'end') {
          obj.start = response.next;
          bcsvv_render.import(obj, response);
          obj.import();
        } else {
          bcsvv_render.import(obj, response);
        }
      });
    },
    filter: function () {
      obj = this;
        $.post(ajaxurl, {
          action: "backupsavvy_filter",
          method: 'POST',
          option: this.option,
          data: this.name,
          nonce: localVars.nonce
        }, function (response_) {
          var response = $.parseJSON(response_);
          var container = obj.option === 'report' ? '.container.existing-list' : '.container.sites-list';

          $(container).find('.spinner').css('display', 'none');
          if(response.sites.length) {
            if(obj.option === 'report') {
              $(container).find('table#existing-backups tbody').remove().append(response.sites);
              $(container).find('table#existing-backups').append(response.sites);
            }
            else
              $(container).find('table#sites-list tbody').html('').append(response.sites);
          }
        }).fail(function () {
          $('.container.sites-list').find('.spinner').css('display', 'none');
          alert('Load error, try again later')
        })
    },
    progress_bar_one_process: function(step, parts, limit) {
      let width = step;

      let param = {};
      param.text = width;
      param.key = 'one';
      if(step === 1) {
        param.key = 'start_one';
        param.title = 'Backup progress';
        param.info = 'Please wait...'
      }
      if (step === 100)
        param.key = 'completed_one';

      if(parts.info !== undefined)
        param.info = parts.info;

      if(step > limit)
        clearInterval(BcsvvyVars.progress_interval);

      bcsvvNamespace.progress_log(width, param);
    },
    progress_bar_one: function (parts = null) { // progress bar one
      let step = 1;
      let limit = 100;
      if(parts !== null) {
        if(parts.part !== 1) {
          step = Math.round(100 / parts.part);
          if(BcsvvyVars.progress_interval !== null)
            clearInterval(BcsvvyVars.progress_interval);
        }
        limit = Math.round(100 / parts.parts) * parts.part;
        if(limit > 100) limit = 100;
        if(parts.key !== undefined)
          if(parts.key === 'completed') {
            step = 100;
            clearInterval(BcsvvyVars.progress_interval);
          }
      }

      this.progress_bar_one_process(step, parts, limit);
      let that = this;
      BcsvvyVars.progress_interval = setInterval(() => {
        step++;
        that.progress_bar_one_process(step, parts, limit);

      }, 30);
    }
  }

  // sort sites list
  $("table#sites-list").tablesorter({
    headers: {
      3: {
        sorter: false
      }
    }
  });

  // import mainwp sites
  $("form#compare").find("input.import").on("click", function(e) {
    e.preventDefault();
    BCSVVYadditional.import();
  });

  // storages settings
  $('div.tabs-v button').on('click', function () {
    var obj = this;
    $(this).addClass('active').siblings().removeClass('active');
    var id = $(this).attr('id');
    var query = bcsvvNamespace.getQueryParams(document.location.search);
    var unique_id = query.unique;
    $('.tabcontent#' + id).css('display', 'block').addClass('active').siblings('.tabcontent').css('display', 'none').removeClass('active');
    if (!$(this).hasClass('loaded')) {
      $.post(ajaxurl, {
        action: "backupsavvy_load_storage",
        method: 'POST',
        id: id,
        unique_id: unique_id,
        nonce: localVars.nonce
      }, function (response_) {
        response = $.parseJSON(response_);
        $('.tabcontent#' + id).find('.inside').append(response.html);
        $(obj).addClass('loaded');
        $('.spinner').hide();
      });
    }
  });

  // tabs settings
  $('ul.tabs li').css('cursor', 'pointer');

  $('div.t').hide();
  $('ul.tabs li').removeClass('tab-current');
  switch (window.location.hash) {
    case '#scheduller':
      $('div.t3').show();
      $('ul.tabs li.t3').addClass('tab-current');
      break;
    case '#list':
      $('div.t2').show();
      $('ul.tabs li.t2').addClass('tab-current');
      if(!$('ul.tabs li.t2').hasClass('loaded'))
        BCSVVYadditional.load_sites();
      break;
    case '#storage':
      $('div.t4').show();
      $('ul.tabs li.t4').addClass('tab-current');
      break;
    case '#existing':
      $('div.t5').show();
      var t5 = $('ul.tabs li.t5');
      t5.addClass('tab-current');
      if(!t5.hasClass('loaded'))
        t5.addClass('loaded');
      BCSVVYadditional.load_report_list();
      break;
    case '#logs':
      $('div.t6').show();
      $('ul.tabs li.t6').addClass('tab-current');
      break;
    default:
      $('div.t1').show();
      $('ul.tabs li.t1').addClass('tab-current');
  }

  $j('ul.tabs li').click(function () {
    var thisClass = this.className.slice(0, 2);
    // console.log(thisClass)
    $j('div.t').hide();
    $j('div.' + thisClass).show();
    $j('ul.tabs li').removeClass('tab-current');
    $j(this).addClass('tab-current');
    if($(this).hasClass('list')) {
      if(!$(this).hasClass('loaded')) {
        $(this).addClass('loaded');
        BCSVVYadditional.load_sites();
      }
    }
    if($(this).hasClass('existing')) {
      if(!$(this).hasClass('loaded')) {
        $(this).addClass('loaded');
        BCSVVYadditional.load_report_list();
      }
    }
  });

  // filter
  $('.sites-list table.top-options').find('form.filter').on('click', 'input[name="filter"]', function(e) {
    e.preventDefault();
    $(this).closest('table').find('.spinner').css('display', 'block');
    BCSVVYadditional.name = $(this).closest('td').find('input[name=site]').val();
    BCSVVYadditional.filter();
  });

  $('.existing-backups table.top-options').find('form.filter').on('click', 'input[name="filter"]', function(e) {
    e.preventDefault();
    $(this).closest('table').find('.spinner').css('display', 'block');
    BCSVVYadditional.name = $(this).closest('td').find('input[name=site]').val();
    BCSVVYadditional.option = 'report';
    BCSVVYadditional.filter();
  });

  // sites per page
  $('.sites-list table.top-options').find('td.per-page').on('click', 'span:not(.per-page):not(.current)', function () {
    $(this).closest('table').find('.spinner').css('display', 'block');
    $(this).toggleClass('current').siblings('span').removeClass('current');
    BCSVVYadditional.page = 1;
    BCSVVYadditional.num_sites = parseInt($(this).attr('data-num'));
    BCSVVYadditional.load_sites();
  });

  // reports pre page
  $('.existing-backups table.top-options').find('td.per-page').on('click', 'span:not(.per-page):not(.current)', function () {
    $(this).closest('table').find('.spinner').css('display', 'block');
    $(this).toggleClass('current').siblings('span').removeClass('current');
    $('table#existing-backups tbody').remove();
    BCSVVYadditional.page_rep = 1;
    BCSVVYadditional.num_sites_rep = parseInt($(this).attr('data-num'));
    BCSVVYadditional.load_report_list();
  });

  // sites' pager
  $('.container.sites-list .tablenav').on('click', '.tablenav-pages a', function (e) {
    e.preventDefault();
    $(this).closest('.container').find('table.top-options').find('.spinner').css('display', 'block');
    BCSVVYadditional.page = parseInt($(this).attr('data-num'));
    BCSVVYadditional.load_sites();
  });

  // reports' pager
  $('.container.existing-list .tablenav').on('click', '.tablenav-pages a', function (e) {
    e.preventDefault();
    var container = $(this).closest('.container');
    container.find('table.top-options').find('.spinner').css('display', 'block');
    container.find('table#existing-backups tbody').remove();
    BCSVVYadditional.page_rep = parseInt($(this).attr('data-num'));
    BCSVVYadditional.load_report_list();
  });

  $('#add-new-job .title').click(function () {
    $(this).next('form').toggle('slow');
  });

  // google cloud
  $('#google .inside').on('change', '.bcsvvy-upload-json input[type=file]', function (e) {
    e.stopPropagation();
    e.preventDefault();
    var files = this.files;
    if (typeof files == 'undefined') return;

    var data = new FormData();

    $.each(files, function (key, value) {
      data.append(key, value);
    });

    data.append('action', 'bcsvvy_upload_json');

    $.ajax({
      url: localVars.ajax_url,
      type: 'POST',
      data: data,
      cache: false,
      dataType: 'json',
      processData: false,
      contentType: false,
      success: function (respond, status, jqXHR) {

        if (typeof respond.error === 'undefined') {
          if (respond.response === 'success') {
            // check if post id
            // connect to cloud
            // append buckets
          }
        }
        else {
          console.log('Error: ' + respond.error);
        }
      },

      error: function (jqXHR, status, errorThrown) {
        console.log('AJAX error: ' + status, jqXHR);
      }

    });
    return false;
  });


  BCSVVY = {
    step: 1,
    form: '',
    id: [],
    unique: 0,
    active: '',
    total: 0,
    caller: '',
    length_id: 0,
    add_scheduler_job: function () {
      $.post(ajaxurl, {
        action: "backupsavvy_add_new_job",
        method: 'POST',
        data: this.form,
        nonce: localVars.nonce
      }, function (response_) {
        response = $.parseJSON(response_);
        bcsvv_render.add_scheduler_job(false, response);
      });
    },
    save_backup_settings: function () {
      var form = this.form;
      var obj = this;
      this.active = 'save_backup_settings';
      $.post(ajaxurl, {
        action: "backupsavvy_save_backup_settings",
        method: 'POST',
        data: form,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.save_backup_settings(obj, response);
      });
    },
    save_ftp_storage: function () {
      var form = this.form;
      var obj = this;
      this.active = 'save_storage';
      // $('#backupsavvy-settings > .overlay').show();
      // $('#progresssteps').parent('.progressbar').show();
      $.post(ajaxurl, {
        action: "backupsavvy_save_storage",
        method: 'POST',
        data: form,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.save_ftp_storage(obj, response);
        console.log(response.status);
      });
    },
    save_dropbox_storage: function () {
      var form = this.form;
      var obj = this;
      this.active = 'save_storage';
      $.post(ajaxurl, {
        action: "backupsavvy_save_storage",
        method: 'POST',
        data: form,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.save_dropbox_storage(obj, response);
        console.log(response.status);
      });
    },
    save_google_storage: function() {
      var form = this.form;
      var obj = this;
      this.active = 'save_storage';
      $.post(ajaxurl, {
        action: "backupsavvy_save_storage",
        method: 'POST',
        data: form,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.save_google_storage(obj, response);
        console.log(response.status);
      });
    },
    set_default: function () {
      var form = this.form;
      var unique = this.id[0];
      $.post(ajaxurl, {
        action: "backupsavvy_set_default",
        method: 'POST',
        unique: unique,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.set_default(false, response);
      });
    },
    set_default: function () {
      var form = this.form;
      var unique = this.id[0];
      $.post(ajaxurl, {
        action: "backupsavvy_set_default",
        method: 'POST',
        unique: unique,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.set_default(false, response);
      });
    },
    test_con: function () {
      var form = this.form;
      $.post(ajaxurl, {
        action: "backupsavvy_test_con",
        method: 'POST',
        data: form
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.test_con(false, response);
      });
    },
    sync_one: function () {
      var obj = this;
      if (this.caller === 'sync_options')
        obj.length_id = this.id.length;
      else
        bcsvvNamespace.enable_preloader();
      $.post(ajaxurl, {
        action: "backupsavvy_sync_one",
        method: 'POST',
        id: this.id[0],
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.sync_one(obj, response);
      });
    },
    sync: function () { // syncing settings for all sites
      var obj = this;
      this.caller = 'sync';
      if (this.step === 1) {
        var param = {
          key: 'start',
          title: 'Backup progress',
          text: 'Syncing process is started. It can takes some time, please wait'
        };
        bcsvvNamespace.progress_log(false, param);
      }
      $.post(ajaxurl, {
        action: "backupsavvy_sync_process",
        method: 'POST',
        step: obj.step,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.sync(obj, response);
      });
    },
    backup_one: function () {
      this.active = 'backup_one';
      var obj = this;
      var id = this.id[0];
      if (this.caller === 'backup_options') {
        if (obj.length_id <= obj.id.length)
          obj.length_id = obj.length_id + 1;
        id = this.id[obj.length_id - 1];
      }
      // else
      //   bcsvvNamespace.enable_preloader();
      $.post(ajaxurl, {
        action: "backupsavvy_backup_one",
        method: 'POST',
        data: id,
        nonce: localVars.nonce
      }, function (response_) {
        response = $.parseJSON(response_);
        console.log('response');
        console.log(response);
        bcsvv_render.backup_one(obj, response);
      }).fail(function () {
        response = {
          status: 'error',
          site: response.toString(),
          step: obj.step + 1
        };
        bcsvv_render.backup(obj, response);
      });
    },
    upload_one: function () {
      this.active = 'upload_one';
      var obj = this;
      var id = this.id[0];
      if (obj.caller === 'backup_options')
        id = this.id[obj.length_id - 1];
      $.post(ajaxurl, {
        action: "backupsavvy_upload_one",
        method: 'POST',
        data: id,
        nonce: localVars.nonce
      }, function (response_) {
        response = $.parseJSON(response_);
        bcsvv_render.upload_one(obj, response);
      }).fail(function () {
        response = {status: 'error', site: 'backup '};
        bcsvv_render.upload_one(obj, response);
      });
    },
    backup: function () {
      this.caller = 'backup';
      var obj = this;
      if (this.step === 1) {
        var param = {
          key: 'start',
          title: 'Backup progress',
          text: 'Backup process is started. It can takes some time, please wait'
        }
        bcsvvNamespace.progress_log(false, param);
      }
      console.log('step=' + obj.step);
      $.post(ajaxurl, {
        action: 'backupsavvy_backup_process',
        method: 'POST',
        step: this.step,
        nonce: localVars.nonce
      }, function (response_) {
        response = $.parseJSON(response_);
        bcsvv_render.backup(obj, response);
      }).fail(function () {
        response = {
          status: 'error',
          site: 'backup ',
          step: obj.step + 1
        };
        bcsvv_render.backup(obj, response);
      });
    },
    upload: function () {
      this.caller = 'upload';
      var obj = this;
      $.post(ajaxurl, {
        action: 'backupsavvy_upload_one',
        method: 'POST',
        data: obj.id[0],
        nonce: localVars.nonce
      }, function (response_) {
        response = $.parseJSON(response_);
        bcsvv_render.upload(obj, response);
      }).fail(function () {
        response = {status: 'error', site: 'backup '};
        bcsvv_render.upload(obj, response);
      });
    },
    remove_one: function () {
      obj = this;
      $.post(ajaxurl, {
        action: 'backupsavvy_remove_site',
        method: 'POST',
        id: this.id[0],
        nonce: localVars.nonce
      }, function (response_) {
        response = $.parseJSON(response_);
        bcsvv_render.remove_one(obj, response);
      })
    },
    add_site: function () {
      obj = this;
      $("form#settings .spinner").css('display', 'inline-block');
      $.post(ajaxurl, {
        action: "backupsavvy_add_new_site",
        method: 'POST',
        data: this.form,
        nonce: localVars.nonce
      }, function (response_) {
        response = jQuery.parseJSON(response_);
        bcsvv_render.add_site(obj, response);
      });
      return false;
    },
    sync_options: function () {
      bcsvvNamespace.progress_log(false, {
        key: 'start',
        title: 'Sync progress',
        text: 'Sync process is started. It can takes some time, please wait'
      });
      this.sync_one();
    },
    backup_options: function () {
      bcsvvNamespace.progress_log(false, {
        key: 'start',
        title: 'Backup progress',
        text: 'Backup process is started. It can takes some time, please wait'
      });
      this.backup_one();
    },
    backup_download: function () {
      var obj = this;
      $.post(ajaxurl, {
        action: "backupsavvy_download_backup",
        method: 'POST',
        id: this.id,
        unique: this.unique,
        nonce: localVars.nonce
      }, function (response_) {
        var response = jQuery.parseJSON(response_);
        if (response.status === 'success')
          window.location.href = window.location.host +
            '/wp-admin/admin.php?page=backup-savvy-settings&backup-file=' + response.name;
        $('table#existing').find('tr td.action > .btn').each(function() {
          $(this).removeClass('disable');
        });
      });
    },
    save_ftp_unique: function() {
      let form = this.form;
      let unique = this.unique;
      $.post(ajaxurl, {
        action: "backupsavvy_save_ftp_unique",
        method: 'POST',
        unique: unique,
        data: form,
        nonce: localVars.nonce
      }, function (response_) {
        var response = jQuery.parseJSON(response_);
        if(response.status === "site_ftp")
          tb_show( 'Add FTP data of the site to need restore', '/?TB_inline&inlineId=ftp-unique-settings&width=700&height=500' );

        if (response.status === 'success')
          alert('Saved');

        $('table#existing').find('tr td.action > .btn').removeClass('disable');
      });
    },
    log_process: function () {
      $.post(ajaxurl, {
        action: "backupsavvy_log_process",
        method: 'POST',
        data: this.form,
        nonce: localVars.nonce
      }, function (response_) {
        var response = jQuery.parseJSON(response_);
        if (response.status === 'success')
          $('.logs .inside').append(response.result);
        //     window.location.href = window.location.host +
        //         '/wp-admin/admin.php?page=backup-savvy-settings&'+response.action;
        $('table#existing').find('tr td.action > .btn').removeClass('disable');
      });
    },
    compare: function () {
      $.post(ajaxurl, {
        action: "backupsavvy_compare",
        method: 'POST',
        data: this.form,
        nonce: localVars.nonce
      }, function (response_) {
        console.log(response_)
        var response = jQuery.parseJSON(response_);
        var result = '';
        $.each(response, function (i, v) {
          result = result + "\n" + v;
        })
        $('form#compare').find('textarea#result').val(result);
      });
    }
  };

  // count sites
  $.post(ajaxurl, {
    action: "backupsavvy_count_sites",
    method: 'POST',
    nonce: localVars.nonce
  }, function (response_) {
    response = jQuery.parseJSON(response_);
    BCSVVY.total = parseInt(response.number);
  });

  // main wp integration
  $('form#compare').on('click', 'input.compare', function (e) {
    e.preventDefault();
    BCSVVY.form = split_textarea($(this).closest('form'));
    BCSVVY.compare();
  });

  function split_textarea(obj) {
    var lines = obj.find('textarea#list').val().replace(/^[\n\r]+|[\n\r]+$/g, '').split(/[\n\r]+/);

    return lines;
  }

  // add new site
  $('#backupsavvy-settings:not(.single)').on('click', 'form#settings input[type="submit"]', bcsvvNamespace.click_add);

  $('#backupsavvy-settings:not(.single)').on('click', 'input[name="settings_save"]', bcsvvNamespace.click_save_backup);
  $('#backupsavvy-settings').on('click', 'input[name="ftp_save"]', bcsvvNamespace.click_ftp_storage);
  $('#backupsavvy-settings').on('click', 'input[name="dropbox_save"]', bcsvvNamespace.click_save_dropbox);
  // $('#backupsavvy-settings').on('click', 'input[name="google_save"]', bcsvvNamespace.click_save_google);
  $('#backupsavvy-settings').on('click', 'button#test-con', bcsvvNamespace.click_test_con);

  // single requests
  $('#backupsavvy-settings.single').on('click', 'button#make-default', bcsvvNamespace.click_set_default);
  $('#backupsavvy-settings.single').on('click', 'input[name=settings_save]', bcsvvNamespace.click_save_backup);


  // bulk operations

  // sync
  $('#backupsavvy-settings').on('click', '#bulk-op a.bulk-sync', bcsvvNamespace.click_sync);

  // all backups
  $('#backupsavvy-settings').on('click', '#bulk-op a.bulk-backup', bcsvvNamespace.click_backup);

  // create one backup/sync/remove
  var sites_list = 'table#sites-list';
  var action = 'tr td.action'
  $(sites_list).on('click', action + ' a.backup.btn', bcsvvNamespace.click_backup_one);
  $(sites_list).on('click', action + ' a.sync.btn', bcsvvNamespace.click_sync_one);
  $(sites_list).on('click', action + ' a.remove.btn', bcsvvNamespace.click_remove_one);


  $('#backupsavvy-settings').find('.overlay').on('click', '.stop', function () {
    window.location.reload();
  });

  var inputs = {};
  var i = 0;
  function create_inputs(object) {
    var id = $(object).closest('tr').find('td.action div.hidden').attr('id');
    if ($(object).prop('checked') === true) {
      var inp = document.createElement("input");
      inp.setAttribute('type', 'hidden');
      inp.setAttribute('value', id);
      inp.setAttribute('name', 'id[' + i + ']');
      inputs[id] = id;
      i++;
    } else {
      delete inputs[id];
    }

    var size = Object.keys(inputs).length;
    var sbm = $('#backupsavvy-settings #bulk-op').find('form#bulk input[type="submit"]');
    if (size > 0) {
      sbm.removeClass('disable').removeAttr('disabled');
    } else {
      sbm.attr('disabled', 'disabled');
      sbm.addClass('disable');
    }
  }

  $('#backupsavvy-settings #bulk-op').on('submit', 'form', function (e) {
    e.stopPropagation();
    e.preventDefault();
    var size = Object.keys(inputs).length;
    if (size > 0) {
      var select = $(this).find('select option:selected').val();
      BCSVVY.id = Object.values(inputs);
      console.log('bid=' + BCSVVY.id);
      if (select === 'sync') {
        BCSVVY.caller = 'sync_options';
        BCSVVY.sync_options();
      }
      if (select === 'backup') {
        BCSVVY.caller = 'backup_options';
        BCSVVY.backup_options();
      }
    }
  });


  // add new job
  $('#add-new-job form').on('submit', function (e) {
    e.stopPropagation();
    bcsvvNamespace.disable_button($(this).find('input[type="submit"]'));
    $(".spinner").css('visibility', 'visible');
    BCSVVY.form = $(this).serialize();
    BCSVVY.add_scheduler_job();
    return false;
  });

  // download backup
  var existing_list = 'table#existing-backups';
  var action = 'td.action';
  $(existing_list).on('click', action + ' .btn.download', function() {
    if ($(this).hasClass('disable'))
      return false;

    $(this).addClass('disable');
    BCSVVY.id = $(this).attr('data-id');
    BCSVVY.unique = $(this).attr('data-unique');
    BCSVVY.backup_download();
  });

  $("body").on("submit", "form#ftp-site-settings", function (e) {
    e.preventDefault();
    BCSVVY.form = $(this).closest('form').serialize();
    BCSVVY.save_ftp_unique();
  });

  // backup logs
  $('.logs form#log-list').on('click', '.submit-form', function (e) {
    e.preventDefault();
    // $(this).find('.form-submit').addClass('disable');
    BCSVVY.form = $('.logs form#log-list').serialize();
    // BCSVVY.form = $(this).html();
    BCSVVY.log_process();
    return false;
  });


});
