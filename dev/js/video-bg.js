/**
 * @file
 * Global video_bg javascript.
 */

(function ($, Drupal, Modernizr, debounce) {

  'use strict';

  function VideoBg(wrapper, options) {
    this.window = $(window);
    this.document = $(document);
    this.wrapper = wrapper;
    this.settings = $.extend(true, {}, this._defaults, options);
    this.initialize();
  }

  $.extend(VideoBg, /** @lends Drupal.VideoBg */{
    debug: false,

    /**
     * Holds references to instantiated VideoBg objects.
     *
     * @type {Array.<Drupal.VideoBg>}
     */
    instances: [],

    /*
    Load YouTube API and call instance build when finished.
     */
    youtube_api_state: 0, // 0 if unfetched, 1 if fetched, 2 if ready
    get_youtube_api: function (instance) {
      var _this = this;
      if (_this.youtube_api_state === 0) {
        this.log('YouTube API Fetch');
        // Insert YouTube api script.
        _this.youtube_api_state = 1;
        var tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        // On load callback.
        window.onYouTubeIframeAPIReady = $.proxy(function () {
          this.log('YouTube API Ready');
          _this.youtube_api_state = 2;
          instance.build_youtube();
          $(document).trigger('video-bg-youtube-ready');
        }, this);
      }
      else if (_this.youtube_api_state === 1) {
        $(document).on('video-bg-youtube-ready', function () {
          instance.build_youtube();
        });
      }
      else if (_this.youtube_api_state === 2) {
        instance.build_youtube();
      }
    },

    /*
    Logger snippet within VideoBg
     */
    log: function (item, prefix) {
      var _this = this;
      prefix = prefix || '';
      if (!_this.debug) {
        return;
      }
      if (typeof item === 'object') {
        console.log('[VideoBg]' + prefix, item); // eslint-disable-line no-console
      }
      else {
        console.log('[VideoBg]' + prefix + ' ' + item); // eslint-disable-line no-console
      }
    },

    /*
    Error logger snippet within VideoBg
     */
    error: function (item, prefix) {
      prefix = prefix || '';
      if (typeof item === 'object') {
        console.error('[VideoBg]' + prefix, item); // eslint-disable-line no-console
      }
      else {
        console.error('[VideoBg]' + prefix + ' ' + item); // eslint-disable-line no-console
      }
    }
  });

  $.extend(VideoBg.prototype, /** @lends Drupal.VideoBg# */{
    _defaults: {
      position: 'absolute',
      zIndex: '-1',
      video_ratio: false,
      loop: true,
      autoplay: true,
      mute: false,
      mp4: false,
      webm: false,
      ogg: false,
      youtube: false,
      priority: 'html5', // flash || html5
      image: false,
      sizing: 'fil', // fill || adjust
      start: 0
    },

    initialize: function () {
      var _this = this;
      _this.set_wrapper_id();
      _this.set_inner_wrapper();
      _this.set_mobile_support();
      _this.set_video_support();
      _this.set_decision();
      // Make video.
      _this['make_' + _this.decision]();
    },

    /*
    Make HTML video.
     */
    make_html5: function () {
      var _this = this;
      var parameters = (_this.settings.autoplay ? 'autoplay ' : '') + (_this.settings.loop ? 'loop onended="this.play()" ' : '');

      var str = '<video width="100%" height="100%" ' + parameters + '>';

      // mp4
      if (_this.settings.mp4 !== false) {
        str += '<source src="' + _this.settings.mp4 + '" type="video/mp4"></source>';
      }

      // webm
      if (_this.settings.webm !== false) {
        str += '<source src="' + _this.settings.webm + '" type="video/webm"></source>';
      }

      // mp4
      if (_this.settings.ogg !== false) {
        str += '<source src="' + _this.settings.ogg + '" type="video/ogg"></source>';
      }
      str += '</video>';

      // html5 video tag
      _this.video = $(str).css({
        position: 'absolute'
      });

      _this.video_wrapper.append(_this.video);

      _this.player = _this.video.get(0);

      if (_this.settings.muted) {
        _this.mute();
      }

      _this.bind_video_resize();
      return;
    },

    /*
    Initialize YouTube video.
     */
    make_youtube: function () {
      var _this = this;
      _this.video = $('<div id="' + _this.id + '-video" class="video-bg-video"></div>').appendTo(_this.video_wrapper).css({
        position: 'absolute'
      }).hide();

      _this.youtube_ready = false;

      VideoBg.get_youtube_api(_this);
    },

    /*
    Build YouTube video.
     */
    build_youtube: function () {
      var _this = this;
      var parameters = {
        // loop: _this.settings.loop ? 1 : 0,
        loop: 0,
        start: _this.settings.start,
        autoplay: _this.settings.autoplay ? 1 : 0,
        controls: 0,
        disablekb: 1,
        showinfo: 0,
        wmode: 'transparent',
        iv_load_policy: 3,
        modestbranding: 1,
        rel: 0,
        fs: 0
      };

      if (_this.settings.loop) {
        // parameters['playlist'] = _this.settings.youtube;
      }

      _this.youtube_started = 0;
      _this.player = new YT.Player(this.id + '-video', {  // eslint-disable-line no-undef
        height: '100%',
        width: '100%',
        playerVars: parameters,
        videoId: _this.settings.youtube,
        events: {
          onReady: $.proxy(_this.ready_youtube, _this),
          onStateChange: function (e) {
            if (e.data === 1 && _this.youtube_started === 0) {
              _this.youtube_started = 1;
              _this.video.fadeIn();
            }
            if (e.data === 0 && _this.settings.loop) {
              _this.rewind();
              _this.play();
            }
          }
        }
      });
    },

    /*
    Call when YouTube video has been loaded and is ready to play.
     */
    ready_youtube: function () {
      var _this = this;

      _this.youtube_ready = true;
      _this.video = $('#' + _this.id + '-video');

      if (_this.settings.mute) {
        _this.mute();
      }

      _this.bind_video_resize();
    },

    /*
    Bind resize.
     */
    bind_video_resize: function () {
      var _this = this;

      if (_this.settings.video_ratio !== false) {
        _this.window.on('resize.video-bg', {}, debounce(function (event) {
          _this.video_resize();
        }, 100));

        _this.document.on('drupalViewportOffsetChange.toolbar', function (event, offsets) {
          _this.video_resize();
        });

        _this.video_resize();
      }
    },

    /*
    Resize video.
     */
    video_resize: function () {
      var _this = this;
      var w = _this.video_wrapper.width();
      var h = _this.video_wrapper.height();

      var width = w;
      var height = w / _this.settings.video_ratio;

      if (height < h) {
        height = h;
        width = h * _this.settings.video_ratio;
      }

      // Round
      height = Math.ceil(height);
      width = Math.ceil(width);

      // Adjust
      var top = Math.round(h / 2 - height / 2);
      var left = Math.round(w / 2 - width / 2);

      var parameters = {
        width: width + 'px',
        height: height + 'px',
        top: top + 'px',
        left: left + 'px'
      };

      _this.video.css(parameters);
      _this.log('Video resized ' + JSON.stringify(parameters) + '.');
    },

    /*
    Make image.
     */
    make_image: function () {
      // Currently this is not used as we apply a background image to the container.
      return;
    },

    /*
    Make image.
     */
    make_image_background: function () {
      var _this = this;
      if (_this.settings.image === false) {
        return;
      }
      var parameters = {
        backgroundImage: 'url(' + _this.settings.image + ')',
        backgroundSize: 'cover'
      };
      _this.video_wrapper.css(parameters);
    },

    /*
    Video play status.
     */
    isPlaying: function () {
      var _this = this;
      if (_this.decision === 'html5') {
        return !_this.video.paused;
      }
      else if (_this.decision === 'youtube' && _this.youtube_ready) {
        return _this.player.getPlayerState() === 1;
      }
      return false;
    },

    /*
    Play video.
     */
    play: function () {
      var _this = this;
      if (_this.decision === 'html5') {
        _this.player.play();
      }
      else if (_this.decision === 'youtube' && _this.youtube_ready) {
        _this.player.playVideo();
      }
      this.log('Video play.');
    },

    /*
    Pause video.
     */
    pause: function () {
      var _this = this;
      if (_this.decision === 'html5') {
        _this.player.pause();
      }
      else if (_this.decision === 'youtube' && _this.youtube_ready) {
        _this.player.pauseVideo();
      }
      this.log('Video pause.');
    },

    /*
    Toggle playback of videos.
     */
    toggle_play: function () {
      var _this = this;
      if (_this.isPlaying()) {
        this.pause();
      }
      else {
        _this.play();
      }
    },

    /*
    Video mute status.
     */
    is_mute: function () {
      var _this = this;
      if (_this.decision === 'html5') {
        return !(_this.player.volume);
      }
      else if (_this.decision === 'youtube' && _this.youtube_ready) {
        return _this.player.is_mute();
      }
      return false;
    },

    /*
    Mute video volume.
     */
    mute: function () {
      var _this = this;
      if (_this.decision === 'html5') {
        _this.player.volume = 0;
      }
      else if (_this.decision === 'youtube' && _this.youtube_ready) {
        _this.player.mute();
      }
      this.log('Video volume mute.');
    },

    /*
    Unmute video volume.
     */
    unmute: function () {
      var _this = this;
      if (_this.decision === 'html5') {
        _this.player.volume = 1;
      }
      else if (_this.decision === 'youtube' && _this.youtube_ready) {
        _this.player.unMute();
      }
      this.log('Video volume unmute.');
    },

    /*
    Toggle video volume.
     */
    toggleMute: function () {
      var _this = this;
      if (_this.is_mute()) {
        _this.unmute();
      }
      else {
        _this.mute();
      }
    },

    /*
    Rewind video to beginning.
     */
    rewind: function () {
      var _this = this;
      if (_this.decision === 'html5') {
        _this.player.currentTime = 0;
      }
      else if (_this.decision === 'youtube' && _this.youtube_ready) {
        _this.player.seekTo(0);
      }
    },

    /*
    Determine what type of display we need.
     */
    set_decision: function () {
      var _this = this;

      _this.decision = 'image';

      // Decide what to use.
      if (!_this.ismobile && (_this.supportsVideo || _this.settings.youtube !== false)) {
        this.decision = _this.settings.priority;
        if (_this.settings.youtube !== false) {
          _this.decision = 'youtube';
        }
        else if (_this.settings.priority === 'html5' && _this.supportsVideo) {
          _this.decision = 'html5';
        }
        else if (this.supportsVideo) {
          _this.decision = 'html5';
        }
      }
      _this.log('Display decision is ' + _this.decision + '.');
    },

    /*
    Determine if mobile.
     */
    set_mobile_support: function () {
      this.ismobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    },

    /*
    Determine video support.
     */
    set_video_support: function () {
      var _this = this;
      _this.supportsVideo = Modernizr.video && ((Modernizr.video.h264 && _this.settings.mp4 !== false) ||
        (Modernizr.video.ogg && _this.settings.ogg !== false) ||
        (Modernizr.video.webm && _this.settings.webm !== false)
      );
      _this.log('HTML5 video ' + (_this.supportsVideo ? 'is' : 'is not') + ' supported or has not been requsted.');
    },

    /*
    Set up inner wrapper.
     */
    set_inner_wrapper: function () {
      var _this = this;
      _this.video_wrapper = $('<div class="video-bg-inner"></div>').appendTo(_this.wrapper).css({
        zIndex: this.settings.zIndex,
        position: this.settings.position,
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        overflow: 'hidden'
      });
      _this.make_image_background();
    },

    /*
    Set the wrapper id if it doesn't have one or get the existing one.
     */
    set_wrapper_id: function () {
      var _this = this;
      if (_this.wrapper.attr('id') != null) {
        _this.id = _this.wrapper.attr('id');
      }
      else {
        _this.id = _this.get_random_id('video-bg');
        _this.wrapper.attr('id', _this.id);
      }
      _this.log('Element id has been set to ' + _this.id + '.');
    },

    /*
    Get a random id by concatenating input string with a random number.
     */
    get_random_id: function (string) {
      return string + '-' + Math.floor((Math.random() * 100000) + 1);
    },

    /*
    Logger snippet within VideoBg
     */
    log: function (item) {
      var _this = this;
      VideoBg.log(item, '[' + _this.id + ']');
    },

    /*
    Error logger snippet within VideoBg
     */
    error: function (item) {
      var _this = this;
      VideoBg.error(item, '[' + _this.id + ']');
    }
  });

  Drupal.behaviors.videoBg = {
    attach: function (context, settings) {
      if (settings.videoBg && settings.videoBg.items) {
        for (var id in settings.videoBg.items) {
          if (settings.videoBg.items[id]) {
            var wrapper = $('#' + id, context).once('video-bg');
            if (wrapper.length) {
              VideoBg.instances.push(new VideoBg(wrapper, settings.videoBg.items[id]));
              // new video_background($('#' + id), settings.videoBg.items[id]);
            }
          }
        }
      }
    }
  };

  // Expose constructor in the public space.
  Drupal.VideoBg = VideoBg;

}(jQuery, Drupal, Modernizr, Drupal.debounce));
