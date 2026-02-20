# E-Invoice Module with CodeMirror Integration

## Setup Instructions

### Install Dependencies

```bash
cd modules/einvoice
npm install
```

### Build CodeMirror Bundle

```bash
# From project root
npm run mix -- --mix-config=modules/einvoice/webpack.mix.js
```

This will create the compiled assets in the module's assets directory.

## Features

- CodeMirror 6 integration for XML editing
- Syntax highlighting for XML documents
- Real-time XML validation and linting
- Error highlighting and diagnostics
- Line numbers and code folding
- Bracket matching

## Troubleshooting

If you encounter any issues with the CodeMirror integration:

1. Check that all dependencies are installed
2. Ensure the bundle is built correctly
3. Check browser console for any JavaScript errors
