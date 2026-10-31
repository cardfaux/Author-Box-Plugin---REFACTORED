/* Custom Author Box — settings page */
(function () {
  'use strict';

  var i18n = window.customAuthorBox || {};

  // Confirm before deleting an author
  document.querySelectorAll('.cab-delete-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(i18n.confirmDelete || 'Delete this author?')) {
        event.preventDefault();
      }
    });
  });

  // Media Library image picker
  var select = document.getElementById('cab-image-select');
  var remove = document.getElementById('cab-image-remove');
  var idInput = document.getElementById('cab-image-id');
  var urlInput = document.getElementById('cab-image');
  var preview = document.getElementById('cab-image-preview');
  var frame;

  if (!select || !idInput || !urlInput || !preview) {
    return;
  }

  function setPreview(src) {
    preview.src = src || '';
    preview.hidden = !src;
    if (remove) {
      remove.hidden = !src;
    }
  }

  select.addEventListener('click', function (event) {
    event.preventDefault();
    if (!window.wp || !window.wp.media) {
      return;
    }

    if (!frame) {
      frame = window.wp.media({
        title: i18n.chooseImage || 'Choose Author Image',
        button: { text: i18n.useImage || 'Use this image' },
        library: { type: 'image' },
        multiple: false
      });

      frame.on('select', function () {
        var file = frame.state().get('selection').first().toJSON();
        var thumb = file.sizes && file.sizes.thumbnail ? file.sizes.thumbnail.url : file.url;
        idInput.value = String(file.id);
        urlInput.value = '';
        urlInput.placeholder = '';
        setPreview(thumb);
      });
    }

    frame.open();
  });

  // Typing a URL replaces any Media Library selection
  urlInput.addEventListener('input', function () {
    idInput.value = '0';
    setPreview(urlInput.value.trim());
  });

  if (remove) {
    remove.addEventListener('click', function (event) {
      event.preventDefault();
      idInput.value = '0';
      urlInput.value = '';
      urlInput.placeholder = 'https://';
      setPreview('');
    });
  }
})();
