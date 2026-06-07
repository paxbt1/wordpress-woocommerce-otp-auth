(function($){
  var timers = new WeakMap();

  function t(key, fallback){
    return (window.WCSA_AUTH && WCSA_AUTH.i18n && WCSA_AUTH.i18n[key]) ? WCSA_AUTH.i18n[key] : fallback;
  }

  function otpLength($wrap){
    var n = parseInt((window.WCSA_AUTH && WCSA_AUTH.otp_length) || $wrap.attr('data-wcsa-otp-length') || 5, 10);
    return Math.max(4, Math.min(6, n || 5));
  }

  function otpTtl(){
    var n = parseInt((window.WCSA_AUTH && WCSA_AUTH.otp_ttl) || 120, 10);
    return Math.max(30, Math.min(900, n || 120));
  }

  function faToEn(v){
    var map={'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
    return (v||'').toString().replace(/[۰-۹٠-٩]/g,function(d){return map[d]||d});
  }

  function showNotice($wrap, type, text){
    var $a=$wrap.find('[data-wcsa-alert]');
    $a.removeClass('wcsa-hidden is-ok is-error is-info').addClass(type==='ok'?'is-ok':(type==='info'?'is-info':'is-error')).html('<span class="wcsa-alert-icon">'+(type==='ok'?'✓':(type==='info'?'i':'!'))+'</span><span>'+ (text||'') +'</span>');
  }
  function hideNotice($wrap){
    $wrap.find('[data-wcsa-alert]').addClass('wcsa-hidden').removeClass('is-ok is-error is-info').text('');
  }

  function step($wrap, name){
    $wrap.find('[data-wcsa-step]').addClass('wcsa-hidden');
    $wrap.find('[data-wcsa-step="'+name+'"]').removeClass('wcsa-hidden');
    $wrap.find('[data-wcsa-step-dot]').removeClass('is-active');
    $wrap.find('[data-wcsa-step-dot="'+name+'"]').addClass('is-active');
  }

  function mobileE164(v){
    v=faToEn(v).replace(/[\s\-]/g,'');
    if(/^09\d{9}$/.test(v))return '+98'+v.substring(1);
    if(/^9\d{9}$/.test(v))return '+98'+v;
    if(/^98\d{10}$/.test(v))return '+'+v;
    if(/^\+98\d{10}$/.test(v))return v;
    return v;
  }
  function mobileNational(v){
    v = mobileE164(v);
    if(/^\+98\d{10}$/.test(v)) return '0' + v.substring(3);
    return v;
  }

  function ajax(data){
    data.nonce = WCSA_AUTH.nonce;
    return $.post(WCSA_AUTH.ajax, data);
  }

  function checkoutModalEnabled(){
    return !!(window.WCSA_AUTH && WCSA_AUTH.checkout_login_required && WCSA_AUTH.checkout_login_modal && !WCSA_AUTH.is_logged_in);
  }

  function checkoutUrl(){
    return (window.WCSA_AUTH && WCSA_AUTH.checkout_url) ? WCSA_AUTH.checkout_url : '';
  }

  function redirectTarget($wrap){
    if($wrap && $wrap.closest('[data-wcsa-checkout-modal]').length && checkoutModalEnabled() && checkoutUrl()) return checkoutUrl();
    return (window.WCSA_AUTH && WCSA_AUTH.redirect_to) ? WCSA_AUTH.redirect_to : '';
  }

  function openCheckoutModal(){
    var $m = $('[data-wcsa-checkout-modal]').first();
    if(!$m.length) return false;
    $m.addClass('is-open').attr('aria-hidden','false');
    $('body').addClass('wcsa-modal-open');
    setTimeout(function(){
      var $phone = $m.find('[data-wcsa-mobile]').first();
      if($phone.length) $phone.trigger('focus');
    }, 80);
    return true;
  }

  function closeCheckoutModal(){
    var $m = $('[data-wcsa-checkout-modal]').first();
    $m.removeClass('is-open').attr('aria-hidden','true');
    $('body').removeClass('wcsa-modal-open');
  }

  function err(xhr, fallback){
    try{ if(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) return xhr.responseJSON.data.message; }catch(e){}
    return fallback || 'خطا رخ داد.';
  }

  function otpBoxes($wrap){
    return $wrap.find('[data-wcsa-otp-boxes]').first();
  }

  function otpStage($wrap){
    return $wrap.find('[data-wcsa-otp-stage]').first();
  }

  function otpInputs($wrap){
    return $wrap.find('[data-wcsa-otp-digit]');
  }

  function buildOtpBoxes($wrap){
    var len = otpLength($wrap);
    var $box = otpBoxes($wrap);
    if(!$box.length) return;
    $box.css('--otp-cols', len);
    $box.empty().removeClass('is-loading is-error is-success');
    for(var i=0;i<len;i++){
      $box.append('<div class="wcsa-otp-shell"><input class="wcsa-otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-wcsa-otp-digit="'+i+'" aria-label="رقم '+(i+1)+' کد تایید"></div>');
    }
    resetOtpAnimation($wrap);
  }

  function otpValue($wrap){
    var out='';
    otpInputs($wrap).each(function(){ out += faToEn($(this).val()).replace(/\D/g,'').substring(0,1); });
    $wrap.find('[data-wcsa-otp]').val(out);
    return out;
  }

  function resetOtpAnimation($wrap){
    var $box = otpBoxes($wrap);
    $box.removeClass('is-loading is-error is-success is-shake');
    $box.find('.wcsa-otp-shell').each(function(){
      this.style.transitionDelay = '';
      this.style.transform = '';
      this.style.opacity = '';
    });
    var $check = $wrap.find('[data-wcsa-otp-check]');
    $check.removeClass('is-visible');
    $check.find('path').css({'stroke-dasharray':'','stroke-dashoffset':''});
  }

  function resetOtp($wrap){
    resetOtpAnimation($wrap);
    otpInputs($wrap).val('').prop('disabled', false).removeClass('is-invalid');
    $wrap.find('[data-wcsa-otp]').val('');
    setTimeout(function(){ otpInputs($wrap).first().trigger('focus'); }, 80);
  }

  function setOtpLoading($wrap, loading){
    otpInputs($wrap).prop('disabled', !!loading);
    $wrap.find('[data-wcsa-timer]').toggleClass('is-loading', !!loading);
    otpBoxes($wrap).toggleClass('is-loading', !!loading).removeClass(loading ? 'is-error' : '');
  }

  function successAnimation($wrap){
    var d = $.Deferred();
    var $box = otpBoxes($wrap);
    var $stage = otpStage($wrap);
    var $shells = $box.find('.wcsa-otp-shell');
    var $check = $wrap.find('[data-wcsa-otp-check]');

    $box.removeClass('is-loading is-error is-shake').addClass('is-success');

    if(!$stage.length || !$shells.length || !$check.length){
      d.resolve();
      return d.promise();
    }

    var sr = $stage[0].getBoundingClientRect();
    var cx = sr.left + sr.width / 2;
    var cy = sr.top + sr.height / 2;

    $shells.each(function(index){
      var rect = this.getBoundingClientRect();
      var sx = rect.left + rect.width / 2;
      var sy = rect.top + rect.height / 2;
      this.style.transitionDelay = (index * 25) + 'ms';
      this.style.transform = 'translate(' + (cx - sx) + 'px,' + (cy - sy) + 'px) scale(.18)';
      this.style.opacity = '0';
    });

    setTimeout(function(){
      var $path = $check.find('path');
      $path.css({'stroke-dasharray':'60','stroke-dashoffset':'60'});
      $check.addClass('is-visible');
      setTimeout(function(){ $path.css('stroke-dashoffset', '0'); }, 40);
    }, 430);

    setTimeout(function(){ d.resolve(); }, 1050);
    return d.promise();
  }

  function errorAnimation($wrap){
    var $box = otpBoxes($wrap);
    setOtpLoading($wrap, false);
    $box.removeClass('is-loading is-success').addClass('is-error is-shake');
    otpInputs($wrap).addClass('is-invalid');
    setTimeout(function(){
      $box.removeClass('is-shake');
      otpInputs($wrap).val('').prop('disabled', false);
      $wrap.find('[data-wcsa-otp]').val('');
      otpInputs($wrap).first().trigger('focus');
    }, 680);
  }

  function startTimer($wrap){
    var el = $wrap.find('[data-wcsa-timer]').get(0);
    if(!el) return;
    var old = timers.get(el);
    if(old) clearInterval(old);
    var remain = otpTtl();
    function render(){
      if(remain > 0){
        var mm = Math.floor(remain/60).toString().padStart(2,'0');
        var ss = (remain%60).toString().padStart(2,'0');
        $(el).html('<span class="wcsa-countdown-label">امکان ارسال مجدد تا</span><span class="wcsa-countdown-time">'+mm+':'+ss+'</span>');
        remain--;
      }else{
        clearInterval(timers.get(el));
        timers.delete(el);
        $(el).html('<a href="#" class="wcsa-resend-link wcsa-inline-link" data-wcsa-resend>'+t('resend','ارسال مجدد کد')+'</a>');
      }
    }
    render();
    timers.set(el, setInterval(render, 1000));
  }

  function sendCode($wrap, mobile, $btn, isResend){
    hideNotice($wrap);
    if($btn && $btn.length){ if($btn.is('button')){$btn.prop('disabled', true);} $btn.addClass('is-loading is-disabled').attr('aria-disabled','true').text(t('sending','در حال ارسال...')); }
    return ajax({action:'wcsa_send_otp', mobile: mobile})
      .done(function(res){
        if(res && res.success){
          $wrap.data('mobile', mobile);
          $wrap.find('[data-wcsa-otp-mobile]').text(mobileNational(mobile));
          buildOtpBoxes($wrap);
          resetOtp($wrap);
          step($wrap, 'otp');
          startTimer($wrap);
          showNotice($wrap, 'ok', res.data && res.data.test_otp ? ('حالت تست: کد تایید '+res.data.test_otp) : (isResend ? 'کد تایید مجدداً ارسال شد.' : 'کد تایید ارسال شد.'));
        }else{
          showNotice($wrap, 'error', (res.data && res.data.message) || 'ارسال کد ناموفق بود.');
        }
      })
      .fail(function(x){ showNotice($wrap, 'error', err(x, 'ارسال کد ناموفق بود.')); })
      .always(function(){ if($btn && $btn.length){ if($btn.is('button')){$btn.prop('disabled', false).text('ارسال کد تایید');} else {$btn.text(t('resend','ارسال مجدد کد'));} $btn.removeClass('is-loading is-disabled').removeAttr('aria-disabled'); } });
  }

  function verifyOtp($wrap){
    var code = otpValue($wrap);
    if(code.length !== otpLength($wrap)) return;
    hideNotice($wrap);
    setOtpLoading($wrap, true);
    ajax({action:'wcsa_verify_otp', mobile:$wrap.data('mobile'), otp:code, redirect_to:redirectTarget($wrap)})
      .done(function(res){
        if(res && res.success){
          if(res.data.status === 'logged_in'){
            successAnimation($wrap).done(function(){
              showNotice($wrap, 'ok', 'کد تایید شد. در حال ورود...');
              setTimeout(function(){ location.href = res.data.redirect || location.href; }, 350);
            });
            return;
          }
          if(res.data.status === 'needs_register'){
            $wrap.data('register_token', res.data.register_token);
            successAnimation($wrap).done(function(){
              hideNotice($wrap);
              step($wrap, 'register');
              setTimeout(function(){ $wrap.find('[data-wcsa-first]').trigger('focus'); }, 80);
            });
            return;
          }
        }
        showNotice($wrap, 'error', (res.data && res.data.message) || 'کد وارد شده صحیح نیست.');
        errorAnimation($wrap);
      })
      .fail(function(x){
        showNotice($wrap, 'error', err(x, 'کد وارد شده صحیح نیست.'));
        errorAnimation($wrap);
      });
  }

  $(document).on('click','[data-wcsa-send]',function(){
    var $wrap=$(this).closest('.wcsa-shell');
    var mob=mobileE164($wrap.find('[data-wcsa-mobile]').val());
    sendCode($wrap, mob, $(this), false);
  });

  $(document).on('click','[data-wcsa-resend]',function(e){
    e.preventDefault();
    if($(this).hasClass('is-disabled')) return;
    var $wrap=$(this).closest('.wcsa-shell');
    var mob=$wrap.data('mobile');
    if(!mob){ step($wrap,'mobile'); return; }
    sendCode($wrap, mob, $(this), true);
  });

  $(document).on('click','[data-wcsa-back]',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.wcsa-shell');
    hideNotice($wrap);
    step($wrap,'mobile');
    setTimeout(function(){ $wrap.find('[data-wcsa-mobile]').trigger('focus'); }, 50);
  });

  $(document).on('input','[data-wcsa-mobile]',function(){
    var v = faToEn($(this).val()).replace(/\D/g,'').substring(0,11);
    $(this).val(v);
  });

  $(document).on('input','[data-wcsa-otp-digit]',function(){
    var $this=$(this), $wrap=$this.closest('.wcsa-shell');
    var v=faToEn($this.val()).replace(/\D/g,'');
    if(v.length > 1){
      var chars=v.split('');
      var start=parseInt($this.attr('data-wcsa-otp-digit')||0,10);
      otpInputs($wrap).each(function(i){ if(i>=start && chars.length) $(this).val(chars.shift()); });
    }else{
      $this.val(v.substring(0,1));
    }
    otpInputs($wrap).removeClass('is-invalid');
    otpBoxes($wrap).removeClass('is-error is-shake');
    var current = otpValue($wrap);
    if(current.length < otpLength($wrap)){
      var idx=parseInt($this.attr('data-wcsa-otp-digit')||0,10);
      var $next=otpInputs($wrap).eq(idx+1);
      if($this.val() && $next.length) $next.trigger('focus');
      return;
    }
    verifyOtp($wrap);
  });

  $(document).on('keydown','[data-wcsa-otp-digit]',function(e){
    var $this=$(this), $wrap=$this.closest('.wcsa-shell'), idx=parseInt($this.attr('data-wcsa-otp-digit')||0,10), $prev=otpInputs($wrap).eq(idx-1), $next=otpInputs($wrap).eq(idx+1);
    if(e.key === 'Backspace' && !$this.val() && $prev.length){ $prev.val('').trigger('focus'); e.preventDefault(); }
    if(e.key === 'ArrowLeft' && $next.length){ $next.trigger('focus'); e.preventDefault(); }
    if(e.key === 'ArrowRight' && $prev.length){ $prev.trigger('focus'); e.preventDefault(); }
  });

  $(document).on('paste','[data-wcsa-otp-digit]',function(e){
    var $wrap=$(this).closest('.wcsa-shell');
    var text=(e.originalEvent.clipboardData || window.clipboardData).getData('text');
    text=faToEn(text).replace(/\D/g,'').substring(0, otpLength($wrap));
    if(!text) return;
    e.preventDefault();
    otpInputs($wrap).each(function(i){ $(this).val(text[i] || ''); });
    if(text.length === otpLength($wrap)) verifyOtp($wrap);
  });

  $(document).on('click','[data-wcsa-register]',function(){
    var $wrap=$(this).closest('.wcsa-shell');
    var $btn=$(this);
    if($btn.hasClass('is-retry')){
      hideNotice($wrap);
      $btn.removeClass('is-retry').text('ثبت‌نام و ورود');
      $wrap.removeData('register_token');
      resetOtp($wrap);
      step($wrap,'mobile');
      setTimeout(function(){ $wrap.find('[data-wcsa-mobile]').trigger('focus'); }, 80);
      return;
    }
    hideNotice($wrap);
    $btn.prop('disabled',true).addClass('is-loading').text(t('registering','در حال ثبت‌نام...'));
    ajax({
      action:'wcsa_complete_register',
      register_token:$wrap.data('register_token'),
      first_name:$wrap.find('[data-wcsa-first]').val(),
      last_name:$wrap.find('[data-wcsa-last]').val(),
      national_code:$wrap.find('[data-wcsa-national]').val(),
      redirect_to:redirectTarget($wrap)
    }).done(function(res){
      if(res && res.success){
        showNotice($wrap, 'ok', 'ثبت‌نام موفق بود. در حال انتقال...');
        location.href=res.data.redirect||location.href;
        return;
      }
      var msg=(res.data&&res.data.message)||'ثبت‌نام ناموفق بود.';
      showNotice($wrap,'error',msg);
      if(msg.indexOf('جلسه ثبت‌نام منقضی') !== -1 || msg.indexOf('منقضی') !== -1){
        $btn.addClass('is-retry').text('تلاش مجدد');
      }
    }).fail(function(x){
      var msg=err(x,'ثبت‌نام ناموفق بود.');
      showNotice($wrap,'error',msg);
      if(msg.indexOf('جلسه ثبت‌نام منقضی') !== -1 || msg.indexOf('منقضی') !== -1){
        $btn.addClass('is-retry').text('تلاش مجدد');
      }
    }).always(function(){
      $btn.prop('disabled',false).removeClass('is-loading');
      if(!$btn.hasClass('is-retry')) $btn.text('ثبت‌نام و ورود');
    });
  });





  function hasExistingWcsaAuth(){
    var phpMarkers = [
      '.wcsa-woocommerce-shortcode-auth',
      '.wcsa-woocommerce-content-auth',
      '.wcsa-woocommerce-pre-shortcode-auth',
      '.wcsa-woocommerce-do-shortcode-auth',
      '.wcsa-wc-fallback-auth',
      '.wcsa-already'
    ].join(',');

    if($(phpMarkers).length) return true;

    var $shells = $('.wcsa-shell').filter(function(){
      return !$(this).closest('template, #wcsa-theme-form-template, #wcsa-woocommerce-form-template, [data-wcsa-checkout-modal]').length;
    });

    return $shells.length > 0;
  }


  function replaceThemeForms(){
    if(hasExistingWcsaAuth()) return;
    if(!(window.WCSA_AUTH && WCSA_AUTH.replace_theme_forms)) return;
    if($('body').hasClass('wcsa-standalone-login')) return;
    if($('body').hasClass('logged-in')) return;

    var $template = $('#wcsa-theme-form-template');
    if(!$template.length) return;

    var selectors = [
      // WordPress core
      'form#loginform',
      'form#registerform',
      'form[action*="wp-login.php"]',

      // WooCommerce
      '.woocommerce form.woocommerce-form-login',
      '.woocommerce form.woocommerce-form-register',
      '.woocommerce form.login',
      '.woocommerce form.register',
      '.woocommerce-account form.login',
      '.woocommerce-account form.register',

      // Easy Digital Downloads
      'form#edd_login_form',
      'form#edd_register_form',
      'form.edd_login_form',
      'form.edd_register_form',
      '.edd_form form',
      'form.edd_form',

      // Common themes/builders/plugins
      'form.login',
      'form.register',
      'form.login-form',
      'form.register-form',
      'form.sign-in-form',
      'form.sign-up-form',
      'form.signin-form',
      'form.signup-form',
      'form.user-login-form',
      'form.user-register-form',
      'form.member-login-form',
      'form.member-register-form',
      'form.um-login',
      'form.um-register',
      'form.tutor-login-form',
      'form.tutor-registration-form',
      'form.elementor-login',
      '.login form',
      '.register form',
      '.signin form',
      '.signup form',
      '.auth form',
      '.account-login form',
      '.account-register form',
      '.user-login form',
      '.user-register form',
      '.customer-login form',
      '.customer-register form',
      '.member-login form',
      '.member-register form'
    ];

    var $candidates = $(selectors.join(',')).filter(function(){
      return isAuthFormCandidate($(this));
    });

    if(!$candidates.length) return;

    var $anchor = findBestAuthAnchor($candidates);
    if(!$anchor.length) $anchor = $candidates.first();

    if(!$('.wcsa-theme-replacement--mounted').length){
      var $mount = $('<div class="wcsa-theme-replacement wcsa-theme-replacement--mounted"></div>').html($template.html());
      $anchor.before($mount);
      $mount.find('.wcsa-shell').each(function(){ buildOtpBoxes($(this)); });
    }

    $candidates.each(function(){
      hideOriginalAuthForm($(this));
    });
  }

  function isAuthFormCandidate($f){
    if(!$f || !$f.length) return false;
    if($f.data('wcsaProcessed')) return false;
    if($f.closest('.wcsa-shell, .wcsa-theme-replacement, #wcsa-theme-form-template, #wpadminbar, .elementor-editor-active, .elementor-editor').length) return false;

    // Do not touch real checkout/guest checkout forms unless the dedicated checkout guard is enabled.
    if($f.is('form.checkout, form.woocommerce-checkout, form.edd_form_cart') || $f.closest('.woocommerce-checkout, .checkout, #edd_checkout_form_wrap, .edd-checkout').length) return false;

    // Avoid replacing unrelated forms.
    var negative = 'search|newsletter|subscribe|coupon|cart|filter|comment|review|contact|payment|order|address|shipping|billing|lostpassword|reset|password-reset|forgot';
    var ident = (($f.attr('id') || '') + ' ' + ($f.attr('class') || '') + ' ' + ($f.attr('action') || '')).toLowerCase();
    if(new RegExp(negative).test(ident)) return false;

    var positive = 'login|log-in|signin|sign-in|register|signup|sign-up|auth|account|customer|member|user-login|user-register|edd_login|edd_register|woocommerce-form-login|woocommerce-form-register|um-login|um-register|digits|otp';
    var classOrActionHit = new RegExp(positive).test(ident);

    var hasPassword = $f.find('input[type="password"], input[name="pwd"], input[name="password"], input[name="user_pass"]').length > 0;
    var hasUserField = $f.find('input[name="log"], input[name="user_login"], input[name="username"], input[name="login"], input[name="email"], input[type="email"], input[type="tel"], input[name*="phone"], input[name*="mobile"]').length > 0;
    var hasRegisterField = $f.find('input[name="user_email"], input[name="edd_user_email"], input[name="email"], input[type="email"], input[name*="first"], input[name*="last"]').length > 0;
    var submitText = ($f.find('button[type="submit"], input[type="submit"], button').first().text() || $f.find('input[type="submit"]').first().val() || '').toString().toLowerCase();
    var submitHit = /(login|log in|sign in|register|sign up|ورود|عضویت|ثبت نام|ثبت‌نام)/.test(submitText);

    return classOrActionHit || (hasPassword && hasUserField) || (hasRegisterField && submitHit);
  }

  function findBestAuthAnchor($forms){
    var wrappers = [
      '.woocommerce .u-columns',
      '.woocommerce #customer_login',
      '.woocommerce-account .woocommerce',
      '#edd_login_form',
      '#edd_register_form',
      '.edd_form',
      '.login-register',
      '.login-register-wrapper',
      '.auth-wrapper',
      '.auth',
      '.account-login',
      '.account-register',
      '.customer-login',
      '.customer-register',
      '.user-login',
      '.user-register',
      '.member-login',
      '.member-register',
      '.login',
      '.register'
    ].join(',');
    var $first = $forms.first();
    var $wrapper = $first.closest(wrappers);
    return $wrapper.length ? $wrapper.first() : $first;
  }

  function hideOriginalAuthForm($form){
    $form.data('wcsaProcessed', true).attr('data-wcsa-replaced','1').attr('hidden','hidden').addClass('wcsa-hidden-original').css('display','none');

    var $near = $form.closest('.woocommerce .u-columns, #customer_login, .edd_form, .login-register, .login-register-wrapper, .auth-wrapper, .auth, .account-login, .account-register, .customer-login, .customer-register, .user-login, .user-register, .member-login, .member-register, .login, .register');
    if($near.length){
      $near.addClass('wcsa-replaced-block');
      // Keep the wrapper invisible, but not if it contains our mounted form.
      if(!$near.find('.wcsa-theme-replacement').length){
        $near.hide();
      }
    }
  }


  function replaceWooCommerceAuthForms(){
    if(hasExistingWcsaAuth()) return;
    if(!(window.WCSA_AUTH && WCSA_AUTH.replace_woocommerce_forms)) return;
    if($('body').hasClass('logged-in') || $('body').hasClass('wcsa-standalone-login')) return;
    if($('.wcsa-wc-hard-replacement--mounted, .wcsa-theme-replacement--mounted, .wcsa-wc-fallback-auth').length) return;

    var $template = $('#wcsa-woocommerce-form-template');
    if(!$template.length) $template = $('#wcsa-theme-form-template');
    if(!$template.length) return;

    var $wooRoot = $('.woocommerce-account .woocommerce, body.woocommerce-account .entry-content, body.woocommerce-account main, body.woocommerce-account .site-content').first();
    var $forms = $('.woocommerce form.woocommerce-form-login, .woocommerce form.woocommerce-form-register, .woocommerce form.login, .woocommerce form.register, #customer_login form');
    if(!$wooRoot.length && !$forms.length) return;

    var $mount = $('<div class="wcsa-wc-hard-replacement wcsa-wc-hard-replacement--mounted"></div>').html($template.html());

    if($forms.length){
      var $anchor = $forms.first().closest('#customer_login, .u-columns, .woocommerce, .entry-content');
      if(!$anchor.length) $anchor = $forms.first();
      $anchor.before($mount);
      $anchor.addClass('wcsa-replaced-block').hide();
    } else {
      $wooRoot.prepend($mount);
    }

    $('.woocommerce form.woocommerce-form-login, .woocommerce form.woocommerce-form-register, .woocommerce form.login, .woocommerce form.register, #customer_login').not('.wcsa-shell *').hide().attr('data-wcsa-replaced','1');
    $mount.find('.wcsa-shell').each(function(){ buildOtpBoxes($(this)); });
  }

  function observeThemeAuthForms(){
    if(!(window.WCSA_AUTH && WCSA_AUTH.replace_theme_forms)) return;
    if(!('MutationObserver' in window)) return;
    var pending = null;
    var observer = new MutationObserver(function(){
      clearTimeout(pending);
      pending = setTimeout(function(){ if(hasExistingWcsaAuth()) return; replaceWooCommerceAuthForms(); replaceThemeForms(); }, 120);
    });
    observer.observe(document.body, {childList:true, subtree:true});
  }



  $(document).on('click','[data-wcsa-checkout-close]',function(e){
    e.preventDefault();
    closeCheckoutModal();
  });

  $(document).on('keydown',function(e){
    if(e.key === 'Escape') closeCheckoutModal();
  });

  $(document).on('click','a.checkout-button, a.wc-forward, a[href*="checkout"], a[href*="checkout/"]',function(e){
    if(!checkoutModalEnabled()) return;
    var href = ($(this).attr('href') || '').toLowerCase();
    var isCheckout = href.indexOf('checkout') !== -1 || $(this).hasClass('checkout-button');
    if(!isCheckout) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    openCheckoutModal();
  });

  $(document).on('submit','form.checkout',function(e){
    if(!checkoutModalEnabled()) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    openCheckoutModal();
  });

  function maybeAutoOpenCheckoutModal(){
    if(!checkoutModalEnabled()) return;
    var path = (window.location.pathname || '').toLowerCase();
    if(path.indexOf('checkout') !== -1 || $('body').hasClass('woocommerce-checkout')){
      openCheckoutModal();
    }
  }

  $(function(){ $('.wcsa-shell').each(function(){ buildOtpBoxes($(this)); }); replaceWooCommerceAuthForms(); replaceThemeForms(); observeThemeAuthForms(); setTimeout(replaceWooCommerceAuthForms, 250); setTimeout(replaceWooCommerceAuthForms, 900); maybeAutoOpenCheckoutModal(); });
})(jQuery);
