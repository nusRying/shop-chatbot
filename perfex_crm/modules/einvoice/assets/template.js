/* 3.3.1 */ 
document.addEventListener('DOMContentLoaded', function() {
  // Get the textarea element
  const textarea = document.querySelector('.ace-editor');

  if (!textarea) return;

  // Create container for CodeMirror
  const container = document.createElement('div');
  container.id = 'codemirror-container';
  textarea.parentNode.insertBefore(container, textarea);

  // Hide the original textarea
  textarea.style.display = 'none';

  // Get initial content type
  const contentTypeSelect = document.getElementById('content_type');
  let currentContentType = contentTypeSelect ? contentTypeSelect.value.toLowerCase() : 'xml';
  
  // Sync CodeMirror content with textarea on every change (including paste)
  function syncTextarea(content) {
    textarea.value = content;
  }
  
  // Initialize CodeMirror with the textarea content and content type
  let editor = window.initCodeMirror('codemirror-container', textarea.value, false, currentContentType, syncTextarea);

  // Add error display element
  const errorDisplay = document.getElementById('content-error');
  container.appendChild(errorDisplay);

  // Fallback: periodic sync in case events are missed
  let lastContent = textarea.value;
  setInterval(() => {
    if (editor) {
      const currentContent = window.getCodeMirrorValue(editor);
      if (currentContent !== lastContent) {
        textarea.value = currentContent;
        lastContent = currentContent;
      }
    }
  }, 100); // Check every 100ms

  // Function to reinitialize editor with new content type
  function reinitializeEditor(newContentType) {
    if (!editor) return;
    
    // Get current editor content
    const currentContent = window.getCodeMirrorValue(editor);
    
    // Clean up event listeners if they exist
    if (editor._cleanupCallback) {
      editor._cleanupCallback();
    }
    
    // Destroy the current editor
    editor.destroy();
    
    // Clear the container
    container.innerHTML = '';
    container.appendChild(errorDisplay);
    
    // Create new editor with the new content type and sync callback
    editor = window.initCodeMirror('codemirror-container', currentContent, false, newContentType.toLowerCase(), syncTextarea);
    currentContentType = newContentType.toLowerCase();
  }

  // Add event listener for content type changes
  if (contentTypeSelect) {
    contentTypeSelect.addEventListener('change', function() {
      const newContentType = this.value.toLowerCase();
      if (newContentType !== currentContentType) {
        reinitializeEditor(newContentType);
      }
    });
  }

  // Update the textarea value when the form is submitted
  document.getElementById('template-form').addEventListener('submit', function (e) {
    const value = window.getCodeMirrorValue(editor);
    textarea.value = value;
    
    const contentType = document.getElementById('content_type').value;

    // Final XML validation before submit (redundant with linting, but an extra safety check)
    try {
      if (contentType.toUpperCase() === 'JSON') {
        try {
          if (contentType.toUpperCase() === 'JSON') {
            validateJSON(value, errorDisplay);
          } else {
            validateXML(value, errorDisplay);
          }
        } catch (error) {
          e.preventDefault();
          showError(errorDisplay, contentType.toUpperCase() === 'JSON' ?
              'JSON Error: ' + error.message :
          'XML Error: ' + error.message);
        return false;
      }
      }

      function validateJSON(jsonString, errorDisplay) {
        JSON.parse(jsonString);
        errorDisplay.classList.add('hide');
      }

      function validateXML(xmlString, errorDisplay) {
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlString, 'text/xml');
        const parserError = xmlDoc.getElementsByTagName('parsererror');

        if (parserError.length > 0) {
          errorDisplay.classList.remove('hide');
          throw new Error('XML parsing failed');
        }
        errorDisplay.classList.add('hide');
      }
      
      function showError(errorDisplay, message) {
        errorDisplay.classList.remove('hide');
        errorDisplay.textContent = message;
      }
    } catch (error) {
      e.preventDefault();
      errorDisplay.textContent = 'XML Error: ' + error.message;
      return false;
    }
  });

  container.addEventListener('focusin', function() {
    $.Shortcuts.stop();
  });

  container.addEventListener('focusout', function() {
    $.Shortcuts.start();
  });
});
