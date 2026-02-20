/* 3.3.1 */ 

// Import only the necessary CodeMirror modules
import {EditorState} from '@codemirror/state';
import {EditorView, keymap, lineNumbers} from '@codemirror/view';
import {defaultKeymap, history, historyKeymap, undo, redo} from '@codemirror/commands';
import {syntaxHighlighting, defaultHighlightStyle, bracketMatching, foldGutter, indentUnit} from '@codemirror/language';
import {linter, lintGutter} from '@codemirror/lint';
import {xml} from '@codemirror/lang-xml';
import {json} from '@codemirror/lang-json';

// Adjust editor height based on content
function adjustEditorHeight(view) {
  // Get the editor container element
  const editorElement = view.dom;

  // Get total content height (in pixels)
  const contentHeight = view.contentHeight;

  // Calculate available space (80% of viewport height)
  const availableHeight = window.innerHeight * 0.8;

  // Set height to content height, but not less than 50vh and not more than available height
  const minHeight = window.innerHeight * 0.5; // 50vh
  const newHeight = Math.max(minHeight, Math.min(contentHeight, availableHeight));

  // Apply the height
  editorElement.style.height = newHeight + 'px';
}

// Initialize CodeMirror editor with XML or JSON linting
window.initCodeMirror = function(elementId, initialContent, readOnly = false, contentType = 'xml', onChangeCallback = null) {
  // XML linter to validate XML in real-time
  const xmlLinter = linter(view => {
    const doc = view.state.doc.toString();
    const diagnostics = [];

    try {
      const parser = new DOMParser();
      const xmlDoc = parser.parseFromString(doc, 'text/xml');

      // Check for parsing errors
      const parserError = xmlDoc.getElementsByTagName('parsererror');
      if (parserError.length > 0) {
        // Try to extract line number from error message
        const errorText = parserError[0].textContent;
        const lineMatch = errorText.match(/line (\d+)/i);
        const line = lineMatch ? parseInt(lineMatch[1], 10) - 1 : 0;

        // Get the position in the document
        const pos = view.state.doc.line(Math.max(1, line)).from;

        diagnostics.push({
          from: pos,
          to: pos,
          severity: 'error',
          message: 'XML Error: ' + errorText.split('\n')[0]
        });
      }
    } catch (e) {
      diagnostics.push({
        from: 0,
        to: 0,
        severity: 'error',
        message: 'XML Error: ' + e.message
      });
    }

    return diagnostics;
  });

  // JSON linter to validate JSON in real-time
  const jsonLinter = linter(view => {
    const doc = view.state.doc.toString();
    const diagnostics = [];

    try {
      // Remove all mustache syntax before validating JSON
      // This handles:
      // - {{variable}} - simple variables
      // - {{{variable}}} - unescaped variables
      // - {{#section}} - section opening
      // - {{/section}} - section closing
      // - {{^inverted}} - inverted section opening
      // - {{!comment}} - comments
      // - {{>partial}} - partials
      let cleanedDoc = doc
        // Remove mustache comments first: {{! comment }}
        .replace(/\{\{![^}]*\}\}/g, '')
        // Remove triple mustache (unescaped): {{{variable}}}
        .replace(/\{\{\{[^}]*\}\}\}/g, 'MUSTACHE_PLACEHOLDER')
        // Remove section tags: {{#section}}, {{/section}}, {{^inverted}}
        .replace(/\{\{[#/^][^}]*\}\}/g, '')
        // Remove partials: {{>partial}}
        .replace(/\{\{>[^}]*\}\}/g, 'MUSTACHE_PLACEHOLDER')
        // Remove simple variables: {{variable}}
        .replace(/\{\{[^}]*\}\}/g, 'MUSTACHE_PLACEHOLDER');
      
      // Remove trailing commas (same pattern as backend PHP)
      // Pattern matches comma followed by optional whitespace and closing bracket/brace
      let pattern = /,(\s*[}\]])/g;
      let count;
      do {
        const before = cleanedDoc;
        cleanedDoc = cleanedDoc.replace(pattern, '$1');
        count = before !== cleanedDoc;
      } while (count);
      JSON.parse(cleanedDoc);
    } catch (e) {
      // Try to extract line number from error message
      const lineMatch = e.message.match(/line (\d+)/i) || e.message.match(/position (\d+)/i);
      let line = 0;
      let pos = 0;

      if (lineMatch) {
        const position = parseInt(lineMatch[1], 10);
        // For position-based errors, convert to line
        const lines = doc.substring(0, position).split('\n');
        line = Math.max(0, lines.length - 1);
        pos = view.state.doc.line(line + 1).from;
      } else {
        // If we can't determine the line, highlight the first character
        pos = 0;
      }

      diagnostics.push({
        from: pos,
        to: pos,
        severity: 'error',
        message: 'JSON Error: ' + e.message
      });
    }

    return diagnostics;
  });

  // Configure editor extensions (plugins)
  const extensions = [
    syntaxHighlighting(defaultHighlightStyle),
    bracketMatching(),
    foldGutter(),
    lineNumbers(),

    // Language-specific features based on content type
    contentType === 'json' ? json() : xml(),
    lintGutter(),  // Shows lint markers in the gutter
    contentType === 'json' ? jsonLinter : xmlLinter,  // Apply appropriate linter

    // Editor behavior
      history(),     // Enable undo/redo history tracking
      keymap.of([    // Add keyboard shortcuts
        ...historyKeymap,  // Add history-related keyboard shortcuts (Ctrl+Z, Ctrl+Y)
        ...defaultKeymap
      ]),
    indentUnit.of('  '),
    EditorState.tabSize.of(2),
  ];

  // Add read-only mode if specified
  if (readOnly) {
    extensions.push(EditorView.editable.of(false));
  }

  // Create the editor instance
  const element = document.getElementById(elementId);
  if (!element) return;

  const view = new EditorView({
    state: EditorState.create({
      doc: initialContent || '',
      extensions: extensions
    }),
    lineNumbers: true,
    parent: element
  });

  // Add content change listener if callback is provided
  if (onChangeCallback && typeof onChangeCallback === 'function') {
    // Use a more compatible approach with DOM events and MutationObserver
    const editorElement = view.dom;
    
    let lastContent = view.state.doc.toString();
    
    function handleContentChange() {
      const currentContent = view.state.doc.toString();
      if (currentContent !== lastContent) {
        lastContent = currentContent;
        setTimeout(() => onChangeCallback(currentContent), 0);
      }
    }
    
    // Listen for various content change events
    editorElement.addEventListener('input', handleContentChange);
    editorElement.addEventListener('paste', () => {
      setTimeout(handleContentChange, 10); // Small delay for paste to complete
    });
    editorElement.addEventListener('keyup', handleContentChange);
    editorElement.addEventListener('keydown', () => {
      setTimeout(handleContentChange, 0);
    });
    
    // Use MutationObserver as additional safety net
    const observer = new MutationObserver(handleContentChange);
    observer.observe(editorElement, { 
      childList: true, 
      subtree: true, 
      characterData: true 
    });
    
    // Store the cleanup function on the view object
    view._cleanupCallback = function() {
      editorElement.removeEventListener('input', handleContentChange);
      editorElement.removeEventListener('paste', handleContentChange);
      editorElement.removeEventListener('keyup', handleContentChange);
      editorElement.removeEventListener('keydown', handleContentChange);
      observer.disconnect();
    };
  }

  return view;
};

// Helper to get content from editor
window.getCodeMirrorValue = function(editorView) {
  if (!editorView) return '';
  return editorView.state.doc.toString();
};

// Undo the last change in the editor
window.undoCodeMirrorChange = function(editorView) {
  if (!editorView) return;
  undo(editorView);
};

// Redo the last undone change
window.redoCodeMirrorChange = function(editorView) {
  if (!editorView) return;
  redo(editorView);
};

// Check if undo is available
window.canUndoCodeMirror = function(editorView) {
  if (!editorView) return false;
  // Use internal state to check if undo is available
  return editorView.state.field(history.field).undoDepth > 0;
};

// Check if redo is available
window.canRedoCodeMirror = function(editorView) {
  if (!editorView) return false;
  // Use internal state to check if redo is available
  return editorView.state.field(history.field).redoDepth > 0;
};