!function(e,i,t,o){"use strict";function s(i,t){this.window=e(window),this.document=e(document),this.wrapper=i,this.settings=e.extend(!0,{},this._defaults,t),this.initialize()}e.extend(s,{debug:!1,instances:[],vimeo_api_state:0,get_vimeo_api:function(i){if(0===this.vimeo_api_state){this.log("Vimeo API Fetch"),this.vimeo_api_state=1;var t=document.createElement("script");t.src="https://player.vimeo.com/api/player.js",t.onload=e.proxy(function(){this.log("Vimeo API Ready"),this.vimeo_api_state=2,i.build_vimeo(),e(document).trigger("video-bg-vimeo-ready")},this);var o=document.getElementsByTagName("script")[0];o.parentNode.insertBefore(t,o)}else 1===this.vimeo_api_state?e(document).on("video-bg-vimeo-ready",function(){i.build_vimeo()}):2===this.vimeo_api_state&&i.build_vimeo()},youtube_api_state:0,get_youtube_api:function(i){if(0===this.youtube_api_state){this.log("YouTube API Fetch"),this.youtube_api_state=1;var t=document.createElement("script");t.src="https://www.youtube.com/iframe_api";var o=document.getElementsByTagName("script")[0];o.parentNode.insertBefore(t,o),window.onYouTubeIframeAPIReady=e.proxy(function(){this.log("YouTube API Ready"),this.youtube_api_state=2,i.build_youtube(),e(document).trigger("video-bg-youtube-ready")},this)}else 1===this.youtube_api_state?e(document).on("video-bg-youtube-ready",function(){i.build_youtube()}):2===this.youtube_api_state&&i.build_youtube()},log:function(e,i){var t=this;i=i||"",t.debug&&("object"==typeof e?console.log("[VideoBg]"+i,e):console.log("[VideoBg]"+i+" "+e))},error:function(e,i){i=i||"","object"==typeof e?console.error("[VideoBg]"+i,e):console.error("[VideoBg]"+i+" "+e)}}),e.extend(s.prototype,{_defaults:{position:"absolute",zIndex:"-1",video_ratio:!1,loop:!0,autoplay:!0,mute:!1,mp4:!1,webm:!1,ogg:!1,youtube:!1,vimeo:!1,priority:"html5",image:!1,sizing:"fill",start:0},initialize:function(){var e=this;e.set_wrapper_id(),e.set_inner_wrapper(),e.set_mobile_support(),e.set_video_support(),e.set_decision(),e["make_"+e.decision]()},make_html5:function(){var i=this,t=(i.settings.autoplay?"autoplay ":"")+(i.settings.loop?'loop onended="this.play()" ':""),o='<video width="100%" height="100%" '+t+">";!1!==i.settings.mp4&&(o+='<source src="'+i.settings.mp4+'" type="video/mp4"></source>'),!1!==i.settings.webm&&(o+='<source src="'+i.settings.webm+'" type="video/webm"></source>'),!1!==i.settings.ogg&&(o+='<source src="'+i.settings.ogg+'" type="video/ogg"></source>'),o+="</video>",i.video=e(o).css({position:"absolute"}),i.video_wrapper.append(i.video),i.player=i.video.get(0),i.settings.muted&&i.mute(),i.bind_video_resize()},make_vimeo:function(){var i=this;i.video=e('<div id="'+i.id+'-video" class="video-bg-video"></div>').appendTo(i.video_wrapper).css({position:"absolute"}).hide(),i.vimeo_ready=!1,s.get_vimeo_api(i)},build_vimeo:function(){var i=this;i.log("Build Vimeo"),i.player=new Vimeo.Player(i.id+"-video",{id:i.settings.vimeo,autoplay:i.settings.autoplay?1:0,background:!0,loop:!0,byline:!1,portrait:!1}),i.player.ready().then(e.proxy(i.ready_vimeo,i))},ready_vimeo:function(){var i=this;i.vimeo_ready=!0,i.video=e("#"+i.id+"-video"),i.settings.mute&&i.mute(),i.bind_video_resize(),i.video.find("iframe").css({width:"100%",height:"100%"}),i.player.on("timeupdate",function(e){e.seconds>.5&&(i.player.off("timeupdate"),i.video.fadeIn())})},make_youtube:function(){var i=this;i.video=e('<div id="'+i.id+'-video" class="video-bg-video"></div>').appendTo(i.video_wrapper).css({position:"absolute"}).hide(),i.youtube_ready=!1,s.get_youtube_api(i)},build_youtube:function(){var i=this,t={loop:0,start:i.settings.start,autoplay:i.settings.autoplay?1:0,controls:0,disablekb:1,showinfo:0,wmode:"transparent",iv_load_policy:3,modestbranding:1,rel:0,fs:0};i.youtube_started=0,i.player=new YT.Player(i.id+"-video",{height:"100%",width:"100%",playerVars:t,videoId:i.settings.youtube,events:{onReady:e.proxy(i.ready_youtube,i),onStateChange:function(e){1===e.data&&0===i.youtube_started&&(i.youtube_started=1,i.watch_youtube()),0===e.data&&i.settings.loop&&(i.rewind(),i.mute(),i.play())}}}),i.video.hide().fadeIn()},watch_youtube:function(){var e=this;e.player.getCurrentTime()<.75?(e.play(),setTimeout(function(){e.watch_youtube()},10)):e.video.fadeIn()},ready_youtube:function(){var i=this;i.youtube_ready=!0,i.video=e("#"+i.id+"-video"),i.settings.mute&&i.mute(),setTimeout(function(){i.video.fadeIn()},25),i.bind_video_resize()},bind_video_resize:function(){var e=this;!1!==e.settings.video_ratio&&(e.window.on("resize.video-bg",{},o(function(i){e.video_resize()},100)),e.document.on("drupalViewportOffsetChange.toolbar",function(i,t){e.video_resize()}),e.video_resize())},video_resize:function(){var e=this,i=Number(e.video_wrapper.width()),t=Number(e.video_wrapper.height()),o=Number(e.settings.video_ratio.toFixed(2));t||(t=e.calculateRatio(i,o)),i=Math.ceil(i),t=Math.ceil(t);var s={width:i+"px",height:t+"px"};e.document.find("#"+e.id).css(s),e.log("Video resized "+JSON.stringify(s)+".")},make_image:function(){},make_image_background:function(){var e=this;if(!1!==e.settings.image){var i={backgroundImage:"url("+e.settings.image+")",backgroundSize:"cover"};e.video_wrapper.css(i)}},isPlaying:function(){var e=this;return"html5"===e.decision?!e.video.paused:!("youtube"!==e.decision||!e.youtube_ready)&&1===e.player.getPlayerState()},play:function(){var e=this;switch(this.log("Video play."),e.decision){case"html5":case"vimeo":return e.player.play();case"youtube":return e.player.playVideo()}},pause:function(){var e=this;switch(e.decision){case"html5":case"vimeo":e.player.pause();break;case"youtube":e.player.pauseVideo()}this.log("Video pause.")},toggle_play:function(){var e=this;e.isPlaying()?this.pause():e.play()},is_mute:function(){var e=this;switch(e.decision){case"html5":return!e.player.volume;case"youtube":return e.player.is_mute();case"vimeo":return!e.player.getVolume()}return!1},mute:function(){var e=this;switch(e.decision){case"html5":e.player.volume=0;break;case"youtube":e.player.mute();break;case"vimeo":e.player.setVolume(0)}this.log("Video volume mute.")},unmute:function(){var e=this;switch(e.decision){case"html5":e.player.volume=1;break;case"youtube":e.player.unMute();break;case"vimeo":e.player.setVolume(1)}this.log("Video volume unmute.")},toggleMute:function(){var e=this;e.is_mute()?e.unmute():e.mute()},rewind:function(){var e=this;switch(e.decision){case"html5":e.player.currentTime=0;break;case"youtube":e.player.seekTo(0);break;case"vimeo":e.player.setCurrentTime(0)}},set_decision:function(){var e=this;e.decision="image",(e.supportsVideo||!1!==e.settings.youtube||e.settings.vimeo)&&(this.decision=e.settings.priority,!1!==e.settings.youtube?e.decision="youtube":!1!==e.settings.vimeo?e.decision="vimeo":"html5"===e.settings.priority&&e.supportsVideo?e.decision="html5":this.supportsVideo&&(e.decision="html5")),e.log("Display decision is "+e.decision+".")},set_mobile_support:function(){this.ismobile=/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)},set_video_support:function(){var e=this;e.supportsVideo=t.video&&(t.video.h264&&!1!==e.settings.mp4||t.video.ogg&&!1!==e.settings.ogg||t.video.webm&&!1!==e.settings.webm),e.log("HTML5 video "+(e.supportsVideo?"is":"is not")+" supported or has not been requsted.")},set_inner_wrapper:function(){var i=this;i.video_wrapper=e('<div class="video-bg-inner"></div>').appendTo(i.wrapper).css({zIndex:this.settings.zIndex,position:this.settings.position,top:0,left:0,right:0,bottom:0,overflow:"hidden"}),i.make_image_background()},set_wrapper_id:function(){var e=this;null!=e.wrapper.attr("id")?e.id=e.wrapper.attr("id"):(e.id=e.get_random_id("video-bg"),e.wrapper.attr("id",e.id)),e.log("Element id has been set to "+e.id+".")},get_random_id:function(e){return e+"-"+Math.floor(1e5*Math.random()+1)},log:function(e){var i=this;s.log(e,"["+i.id+"]")},error:function(e){var i=this;s.error(e,"["+i.id+"]")},calculateRatio:function(e,i){switch(i){case 1.78:return e/16*9;default:return e/4*3}}}),i.behaviors.videoBg={attach:function(i,t){if(t.videoBg&&t.videoBg.items)for(var o in t.videoBg.items)if(t.videoBg.items[o]){var a=e("#"+o,i).once("video-bg");a.length&&s.instances.push(new s(a,t.videoBg.items[o]))}}},i.VideoBg=s}(jQuery,Drupal,Modernizr,Drupal.debounce);