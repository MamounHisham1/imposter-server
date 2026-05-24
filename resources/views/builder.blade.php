<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Avatar Builder - Position Editor</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Lalezar&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Lalezar', cursive;
      background: #1a0e08;
      color: #f5e6d0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 16px;
    }

    h1 { font-size: 28px; margin-bottom: 8px; color: #d3bfa1; }
    .subtitle { font-size: 16px; color: #8b6914; margin-bottom: 16px; }

    /* ---- MAIN LAYOUT ---- */
    .main-layout {
      display: flex; gap: 24px; max-width: 1200px; width: 100%;
      align-items: flex-start; flex-wrap: wrap; justify-content: center;
    }

    /* ---- EDITOR CANVAS ---- */
    .editor-panel {
      background: #2a1a0e; border: 3px solid #5c3a21; border-radius: 12px;
      padding: 20px; display: flex; flex-direction: column; align-items: center;
    }
    .canvas-area {
      width: 320px; height: 320px; position: relative;
      border: 3px solid #8b6914; border-radius: 10px;
      background: #d3bfa1; overflow: hidden; cursor: crosshair;
    }
    .canvas-area img {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
      object-fit: contain; pointer-events: none; user-select: none;
      -webkit-user-drag: none; outline: none !important;
    }
    .canvas-area .layer-img { outline: none !important; border: none !important; }

    /* Grid overlay */
    .grid-overlay {
      position: absolute; inset: 0; pointer-events: none; opacity: 0.15;
      background-image:
        linear-gradient(#000 1px, transparent 1px),
        linear-gradient(90deg, #000 1px, transparent 1px);
      background-size: 12.5% 12.5%;
    }
    .crosshair {
      position: absolute; pointer-events: none; z-index: 50;
    }
    .crosshair-h, .crosshair-v {
      position: absolute; background: rgba(255,107,0,0.3);
    }
    .crosshair-h { top: 50%; left: 0; right: 0; height: 1px; }
    .crosshair-v { left: 50%; top: 0; bottom: 0; width: 1px; }

    /* Size preview */
    .preview-row {
      display: flex; gap: 16px; margin-top: 16px; align-items: flex-end;
    }
    .preview-box {
      display: flex; flex-direction: column; align-items: center; gap: 4px;
    }
    .preview-box span { font-size: 11px; color: #8b6914; }
    .preview-avatar {
      position: relative; overflow: hidden; border-radius: 8px;
      border: 2px solid #8b6914; background: #d3bfa1;
    }
    .preview-avatar img {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
      object-fit: contain; pointer-events: none;
    }

    /* ---- CONTROLS PANEL ---- */
    .controls-panel {
      background: #2a1a0e; border: 3px solid #5c3a21; border-radius: 12px;
      padding: 20px; min-width: 320px; max-width: 400px;
    }
    .control-section {
      margin-bottom: 16px; padding-bottom: 16px;
      border-bottom: 1px dashed #5c3a21;
    }
    .control-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .control-section h3 {
      font-size: 16px; color: #d3bfa1; margin-bottom: 10px;
    }

    /* Layer selector */
    .layer-tabs { display: flex; gap: 4px; margin-bottom: 12px; flex-wrap: wrap; }
    .layer-tab {
      padding: 6px 14px; font-family: 'Lalezar', cursive; font-size: 14px;
      background: #3a2010; color: #d3bfa1; border: 2px solid #5c3a21;
      cursor: pointer; border-radius: 6px;
    }
    .layer-tab:hover { border-color: #8b6914; }
    .layer-tab.active { background: #8b2500; border-color: #4a1500; color: #e8dcc4; }

    .gender-tabs { display: flex; gap: 4px; margin-bottom: 8px; }
    .gender-tab {
      padding: 3px 10px; font-family: 'Lalezar', cursive; font-size: 12px;
      background: #2a1a0e; color: #8b6914; border: 2px solid #5c3a21;
      cursor: pointer; border-radius: 6px;
    }
    .gender-tab:hover { border-color: #8b6914; }
    .gender-tab.active { background: #5c3a21; border-color: #8b6914; color: #f5e6d0; }
    .gender-tab.active[data-gender="male"] { background: #1a3a5c; border-color: #3a7abd; color: #c8ddf5; }
    .gender-tab.active[data-gender="female"] { background: #5c1a3a; border-color: #bd3a7a; color: #f5c8dd; }

    .gender-badge {
      position: absolute; bottom: 1px; left: 1px;
      width: 16px; height: 16px; border-radius: 50%;
      font-size: 9px; line-height: 16px; text-align: center;
      cursor: pointer; z-index: 2; font-family: sans-serif; font-weight: bold;
      border: 1px solid rgba(0,0,0,0.3);
    }
    .gender-badge.untagged { background: #555; color: #aaa; }
    .gender-badge.male { background: #2a6ab8; color: #fff; }
    .gender-badge.female { background: #b82a6a; color: #fff; }

    /* Item grid */
    .item-grid { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
    .item-thumb {
      width: 48px; height: 48px; border-radius: 6px; border: 2px solid #5c3a21;
      background: #d3bfa1; overflow: hidden; cursor: pointer; position: relative;
    }
    .item-thumb:hover { border-color: #8b6914; }
    .item-thumb.active { border-color: #ff6b00; box-shadow: 0 0 8px rgba(255,107,0,0.4); }
    .item-thumb img { width: 100%; height: 100%; object-fit: contain; }
    .item-thumb .dupe-badge {
      position: absolute; top: 1px; right: 1px; background: #ff6b00;
      color: #fff; font-size: 9px; padding: 0 3px; border-radius: 3px; line-height: 14px;
    }

    /* Position controls */
    .pos-controls { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
    .pos-field {
      display: flex; align-items: center; gap: 4px;
    }
    .pos-field label { font-size: 13px; color: #8b6914; min-width: 16px; }
    .pos-field input[type="number"] {
      width: 60px; padding: 4px 6px; font-family: monospace; font-size: 13px;
      background: #1a0e08; color: #d3bfa1; border: 1px solid #5c3a21;
      border-radius: 4px; text-align: center;
    }
    .pos-field input[type="range"] { width: 80px; accent-color: #ff6b00; }

    /* Buttons */
    .btn {
      font-family: 'Lalezar', cursive; padding: 8px 16px; border-radius: 6px;
      cursor: pointer; font-size: 14px; border: 2px solid; transition: all 0.1s;
    }
    .btn:active { transform: translate(1px, 1px); }
    .btn-primary {
      background: #8b2500; color: #e8dcc4; border-color: #4a1500;
    }
    .btn-primary:hover { background: #a52f00; }
    .btn-secondary {
      background: #3a2010; color: #d3bfa1; border-color: #5c3a21;
    }
    .btn-secondary:hover { background: #4a2a15; }
    .btn-accent {
      background: #8b6914; color: #1a0e08; border-color: #6a4e10;
    }
    .btn-accent:hover { background: #a07818; }
    .btn-danger {
      background: #661a00; color: #e8dcc4; border-color: #4a1200;
    }
    .btn-danger:hover { background: #882200; }

    .btn-row { display: flex; gap: 6px; flex-wrap: wrap; }

    /* Duplicates list */
    .dupes-list { margin-top: 8px; }
    .dupe-item {
      display: flex; align-items: center; gap: 6px;
      background: #1a0e08; padding: 6px 8px; border-radius: 6px; margin-bottom: 4px;
    }
    .dupe-item .dupe-info { flex: 1; font-size: 12px; color: #d3bfa1; }
    .dupe-item .dupe-mini {
      width: 28px; height: 28px; background: #d3bfa1; border-radius: 4px;
      overflow: hidden; border: 1px solid #5c3a21;
    }
    .dupe-item .dupe-mini img { width: 100%; height: 100%; object-fit: contain; }
    .dupe-remove {
      background: none; border: none; color: #ff6b00; cursor: pointer;
      font-size: 16px; padding: 2px 6px;
    }

    /* Config output */
    .config-output {
      background: #1a0e08; border: 1px solid #5c3a21; border-radius: 8px;
      padding: 12px; margin-top: 12px; max-height: 300px; overflow-y: auto;
    }
    .config-output pre {
      font-family: monospace; font-size: 12px; color: #d3bfa1;
      white-space: pre-wrap; word-break: break-all;
    }
    .config-output::-webkit-scrollbar { width: 6px; }
    .config-output::-webkit-scrollbar-track { background: #1a0e08; }
    .config-output::-webkit-scrollbar-thumb { background: #5c3a21; border-radius: 3px; }

    /* Toast */
    .toast {
      position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
      background: #8b2500; color: #e8dcc4; padding: 10px 24px; border-radius: 8px;
      font-family: 'Lalezar', cursive; font-size: 16px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.5); z-index: 9999;
      opacity: 0; transition: opacity 0.3s;
    }
    .toast.show { opacity: 1; }

    /* Paid badge on item thumbs */
    .paid-badge {
      position: absolute; top: 1px; left: 1px; background: #10b981;
      color: #fff; font-size: 9px; padding: 0 3px; border-radius: 3px; line-height: 14px;
      font-family: sans-serif; font-weight: bold;
    }
    .paid-badge.costume-badge { background: #8b5cf6; }

    /* Paid section */
    .paid-section { margin-top: 12px; }
    .paid-mode-tabs { display: flex; gap: 4px; margin-bottom: 10px; }
    .paid-mode-tab {
      flex: 1; padding: 6px 10px; font-family: 'Lalezar', cursive; font-size: 13px;
      background: #3a2010; color: #8b6914; border: 2px solid #5c3a21;
      cursor: pointer; border-radius: 6px; text-align: center;
    }
    .paid-mode-tab:hover { border-color: #8b6914; }
    .paid-mode-tab.active { background: #10b981; border-color: #059669; color: #fff; }
    .paid-mode-tab.active.costume-tab { background: #8b5cf6; border-color: #7c3aed; }

    .price-field {
      display: flex; align-items: center; gap: 6px; margin-bottom: 8px;
    }
    .price-field label { font-size: 13px; color: #d3bfa1; min-width: 40px; }
    .price-field input {
      width: 80px; padding: 4px 8px; font-family: monospace; font-size: 14px;
      background: #1a0e08; color: #d3bfa1; border: 1px solid #5c3a21;
      border-radius: 4px; text-align: center;
    }
    .price-field .currency { font-size: 14px; color: #10b981; font-weight: bold; }

    .costume-list { margin-top: 8px; }
    .costume-item {
      display: flex; align-items: center; gap: 8px;
      background: #1a0e08; padding: 8px 10px; border-radius: 6px; margin-bottom: 6px;
      border: 2px solid transparent;
    }
    .costume-item.selected { border-color: #8b5cf6; background: #1a1028; }
    .costume-item .costume-preview {
      width: 36px; height: 36px; border-radius: 6px; background: #d3bfa1;
      overflow: hidden; border: 1px solid #5c3a21; position: relative; flex-shrink: 0;
    }
    .costume-item .costume-preview img {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
      object-fit: contain; pointer-events: none;
    }
    .costume-item .costume-info { flex: 1; min-width: 0; }
    .costume-item .costume-name {
      font-size: 13px; color: #d3bfa1; white-space: nowrap;
      overflow: hidden; text-overflow: ellipsis;
    }
    .costume-item .costume-price { font-size: 11px; color: #10b981; }
    .costume-item .costume-layers { font-size: 10px; color: #8b6914; }
    .costume-item .costume-remove {
      background: none; border: none; color: #ff6b00; cursor: pointer;
      font-size: 16px; padding: 2px 6px;
    }

    .costume-name-input {
      width: 100%; padding: 5px 8px; font-family: 'Lalezar', cursive; font-size: 13px;
      background: #1a0e08; color: #d3bfa1; border: 1px solid #5c3a21;
      border-radius: 4px; margin-bottom: 8px;
    }

    /* Save indicator */
    .save-status {
      font-size: 12px; color: #666; margin-top: 4px; text-align: center;
    }

    /* Promo code tabs */
    .promo-type-tab {
      flex: 1; padding: 4px 8px; font-family: 'Lalezar', cursive; font-size: 11px;
      background: #3a2010; color: #8b6914; border: 2px solid #5c3a21;
      cursor: pointer; border-radius: 6px; text-align: center;
    }
    .promo-type-tab:hover { border-color: #8b6914; }
    .promo-type-tab.active { background: #8b2500; border-color: #4a1500; color: #e8dcc4; }
    .promo-code-row {
      display: flex; align-items: center; gap: 6px;
      background: #1a0e08; padding: 6px 8px; border-radius: 6px; margin-bottom: 4px;
      font-size: 11px;
    }
    .promo-code-row .promo-code-text {
      font-family: monospace; color: #10b981; font-weight: bold; font-size: 12px;
    }
    .promo-code-row .promo-code-meta {
      flex: 1; color: #8b6914; font-size: 10px;
    }
    .promo-code-row .promo-delete-btn {
      background: none; border: none; color: #ff6b00; cursor: pointer; font-size: 14px; padding: 2px 6px;
    }
  </style>
</head>
<body>

<h1>Avatar Position Editor</h1>
<p class="subtitle">Click a layer, drag to position, duplicate elements, save to game config</p>

<div class="main-layout">
  <!-- LEFT: Editor Canvas -->
  <div class="editor-panel">
    <div class="canvas-area" id="canvas">
      <div class="grid-overlay"></div>
      <div class="crosshair-h"></div>
      <div class="crosshair-v"></div>
      <!-- Layers rendered by JS -->
    </div>

    <div class="preview-row">
      <div class="preview-box">
        <span>40px</span>
        <div class="preview-avatar" id="prev40" style="width:40px;height:40px;"></div>
      </div>
      <div class="preview-box">
        <span>56px</span>
        <div class="preview-avatar" id="prev56" style="width:56px;height:56px;"></div>
      </div>
      <div class="preview-box">
        <span>80px</span>
        <div class="preview-avatar" id="prev80" style="width:80px;height:80px;"></div>
      </div>
      <div class="preview-box">
        <span>120px</span>
        <div class="preview-avatar" id="prev120" style="width:120px;height:120px;"></div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Controls -->
  <div class="controls-panel">
    <!-- Head selector -->
    <div class="control-section">
      <h3>Head (Skin Color)</h3>
      <div class="item-grid" id="headGrid"></div>
    </div>

    <!-- Layer tabs -->
    <div class="control-section">
      <h3>Accessories</h3>
      <div class="gender-tabs" id="genderTabs">
        <div class="gender-tab active" data-gender="all">All</div>
        <div class="gender-tab" data-gender="male">Male</div>
        <div class="gender-tab" data-gender="female">Female</div>
      </div>
      <div class="layer-tabs" id="layerTabs">
        <div class="layer-tab active" data-layer="eyes">Eyes</div>
        <div class="layer-tab" data-layer="hair">Hair</div>
        <div class="layer-tab" data-layer="beard">Beard</div>
      </div>

      <div class="item-grid" id="itemGrid"></div>

      <!-- Upload new element -->
      <div style="margin-top:8px;">
        <input type="file" id="uploadInput" accept="image/png" style="display:none">
        <button class="btn btn-accent" id="uploadBtn" style="width:100%;font-size:13px;padding:6px 12px;">+ Upload New</button>
      </div>

      <div id="posSection" style="display:none;">
        <h3 style="font-size:14px;color:#8b6914;margin-bottom:8px;">Position (drag on canvas or use controls)</h3>
        <div class="pos-controls">
          <div class="pos-field">
            <label>X</label>
            <input type="number" id="posX" value="0" step="1">
            <input type="range" id="posXRange" min="-128" max="128" value="0">
          </div>
        </div>
        <div class="pos-controls">
          <div class="pos-field">
            <label>Y</label>
            <input type="number" id="posY" value="0" step="1">
            <input type="range" id="posYRange" min="-128" max="128" value="0">
          </div>
        </div>
        <div class="pos-controls">
          <div class="pos-field">
            <label>Scale</label>
            <input type="number" id="posScale" value="100" step="1" min="10" max="300">
            <input type="range" id="posScaleRange" min="10" max="300" value="100">
          </div>
        </div>

        <div class="btn-row">
          <button class="btn btn-accent" id="duplicateBtn">Duplicate</button>
          <button class="btn btn-secondary" id="resetPosBtn">Reset</button>
          <button class="btn btn-danger" id="removeBtn">Remove</button>
        </div>

        <div class="dupes-list" id="dupesList"></div>
      </div>
    </div>

    <!-- Paid / Monetization -->
    <div class="control-section">
      <h3>Paid Items</h3>
      <div class="paid-mode-tabs">
        <div class="paid-mode-tab active" data-mode="elements">Elements</div>
        <div class="paid-mode-tab costume-tab" data-mode="costume">Full Costume</div>
      </div>

      <!-- Elements mode: price the selected item -->
      <div id="paidElements">
        <div id="paidElementInfo" style="font-size:12px;color:#8b6914;margin-bottom:8px;">Select an item to set its price</div>
        <div id="paidElementControls" style="display:none;">
          <div class="price-field">
            <label>Price</label>
            <span class="currency">$</span>
            <input type="number" id="paidPrice" min="0" step="0.5" value="0" placeholder="0.00">
          </div>
          <div class="btn-row">
            <button class="btn btn-accent" id="setPaidBtn" style="font-size:12px;padding:5px 12px;">Set as Paid</button>
            <button class="btn btn-danger" id="setFreeBtn" style="font-size:12px;padding:5px 12px;">Set Free</button>
          </div>
        </div>
      </div>

      <!-- Costume mode: lock current selection as a purchasable set -->
      <div id="paidCostume" style="display:none;">
        <input type="text" class="costume-name-input" id="costumeName" placeholder="Costume name (e.g. Royal Knight)">
        <div class="price-field">
          <label>Price</label>
          <span class="currency">$</span>
          <input type="number" id="costumePrice" min="0" step="0.5" value="4.99" placeholder="0.00">
        </div>
        <div style="font-size:11px;color:#8b6914;margin-bottom:8px;">Locks current head + all selected items. Player cannot change anything.</div>
        <div class="btn-row">
          <button class="btn btn-accent" id="createCostumeBtn" style="font-size:12px;padding:5px 12px;">Create Costume</button>
        </div>
        <div class="costume-list" id="costumeList"></div>
      </div>
    </div>

    <!-- Promo Codes -->
    <div class="control-section">
      <h3>Promo Codes</h3>
      <div style="font-size:11px;color:#8b6914;margin-bottom:8px;">Generate redeemable codes for items, costumes, or credits</div>

      <div class="promo-reward-type" style="display:flex;gap:4px;margin-bottom:8px;">
        <button class="promo-type-tab active" data-type="element">Element</button>
        <button class="promo-type-tab" data-type="costume">Costume</button>
        <button class="promo-type-tab" data-type="credits">Credits</button>
      </div>

      <!-- Element reward -->
      <div id="promoElementSelect" class="promo-reward-section">
        <select id="promoElementPicker" style="width:100%;padding:5px 8px;font-family:'Lalezar',cursive;font-size:12px;background:#1a0e08;color:#d3bfa1;border:1px solid #5c3a21;border-radius:4px;margin-bottom:6px;">
          <option value="">-- Pick a paid element --</option>
        </select>
      </div>

      <!-- Costume reward -->
      <div id="promoCostumeSelect" class="promo-reward-section" style="display:none;">
        <select id="promoCostumePicker" style="width:100%;padding:5px 8px;font-family:'Lalezar',cursive;font-size:12px;background:#1a0e08;color:#d3bfa1;border:1px solid #5c3a21;border-radius:4px;margin-bottom:6px;">
          <option value="">-- Pick a costume --</option>
        </select>
      </div>

      <!-- Credits reward -->
      <div id="promoCreditsSelect" class="promo-reward-section" style="display:none;">
        <input type="number" id="promoCreditsAmount" min="1" step="1" value="50" placeholder="Credits amount"
          style="width:100%;padding:5px 8px;font-family:'Lalezar',cursive;font-size:12px;background:#1a0e08;color:#d3bfa1;border:1px solid #5c3a21;border-radius:4px;margin-bottom:6px;">
      </div>

      <div style="display:flex;gap:6px;margin-bottom:6px;">
        <input type="text" id="promoCustomCode" placeholder="Custom code (e.g. YOUTUBER-1)" maxlength="50"
          style="flex:1;padding:4px 6px;font-family:'Lalezar',cursive;font-size:11px;background:#1a0e08;color:#d3bfa1;border:1px solid #5c3a21;border-radius:4px;">
        <input type="number" id="promoMaxUses" min="1" placeholder="Max uses (blank=unlimited)"
          style="flex:1;padding:4px 6px;font-family:'Lalezar',cursive;font-size:11px;background:#1a0e08;color:#d3bfa1;border:1px solid #5c3a21;border-radius:4px;">
      </div>
      <div style="display:flex;gap:6px;margin-bottom:6px;">
        <input type="datetime-local" id="promoExpires"
          style="flex:1;padding:4px 6px;font-family:'Lalezar',cursive;font-size:11px;background:#1a0e08;color:#d3bfa1;border:1px solid #5c3a21;border-radius:4px;">
      </div>

      <button class="btn btn-accent" id="generatePromoBtn" style="width:100%;font-size:12px;padding:5px 12px;">Generate Code</button>

      <div class="promo-codes-list" id="promoCodesList" style="margin-top:10px;max-height:200px;overflow-y:auto;"></div>
    </div>

    <!-- Actions -->
    <div class="control-section">
      <h3>Actions</h3>
      <div class="btn-row">
        <button class="btn btn-primary" id="saveBtn">Save to Game</button>
        <button class="btn btn-secondary" id="copyBtn">Copy Config</button>
        <button class="btn btn-secondary" id="exportBtn">Export JSON</button>
      </div>
      <div class="save-status" id="saveStatus"></div>
    </div>

    <!-- Config output -->
    <div class="control-section">
      <h3>Current Config</h3>
      <div class="config-output">
        <pre id="configPreview"></pre>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const BASE = '/avatars/';
const TOOL_SIZE = 256;
const CANVAS_DISPLAY = 320;
const PIXEL_TO_TOOL = TOOL_SIZE / CANVAS_DISPLAY;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

// Session-authed API helper
async function adminFetch(url, options = {}) {
  const headers = options.headers || {};
  if (options.body && typeof options.body === 'string') {
    headers['Content-Type'] = 'application/json';
  }
  headers['X-CSRF-TOKEN'] = CSRF_TOKEN;
  headers['Accept'] = 'application/json';
  return fetch(url, { ...options, headers, credentials: 'same-origin' });
}

// ── ALIGNMENT DATA (loaded from game config) ──
let ALIGNMENT = {
  eyes: {
    "eye1.png": { scale: 70, x: 0, y: -16 },
    "eye002.png": { scale: 58, x: 0, y: -23 },
    "eye004.png": { scale: 72, x: -16, y: -17 },
    "eye005.png": { scale: 56, x: 0, y: -17 },
    "eye006.png": { scale: 78, x: 0, y: -10 },
    "eye6.png": { scale: 61, x: -33, y: 0 },
    "eye007.png": { scale: 55, x: 0, y: -19 },
    "eye9.png": { scale: 70, x: 0, y: -15 }
  },
  hair: {
    "hair3.png": { scale: 96, x: 0, y: -40 },
    "hair5.png": { scale: 100, x: 0, y: -50 },
    "hair7.png": { scale: 126, x: 0, y: -12 },
    "hair9.png": { scale: 114, x: 0, y: -10 },
    "hair10.png": { scale: 129, x: 0, y: -25 },
    "hair11.png": { scale: 115, x: 0, y: -44 },
    "hair12.png": { scale: 97, x: 0, y: -98 },
    "haur001.png": { scale: 104, x: 0, y: 0 }
  },
  beard: {
    "beard00.png": { scale: 71, x: 22, y: 52 },
    "beard001.png": { scale: 38, x: 0, y: 55 },
    "beard002.png": { scale: 45, x: 3, y: 60 },
    "beard3.png": { scale: 83, x: 0, y: 36 },
    "beard4.png": { scale: 105, x: 0, y: 9 },
    "beard5.png": { scale: 157, x: 0, y: 183 },
    "beard6.png": { scale: 117, x: 0, y: 61 },
    "beard7.png": { scale: 69, x: 0, y: 37 },
    "beard9.png": { scale: 57, x: 0, y: 37 },
    "beard10.png": { scale: 49, x: 0, y: 45 },
    "beard12.png": { scale: 63, x: 0, y: 56 },
    "beard13.png": { scale: 93, x: 0, y: 38 },
    "beard14.png": { scale: 45, x: -23, y: 64 }
  }
};

const HEADS = ['head1.png','head2.png','head3.png','head4.png','head7.png','head8.png'];

function classifyFile(name) {
  if (name.startsWith('eye')) return 'eyes';
  if (name.startsWith('hair') || name.startsWith('haur')) return 'hair';
  if (name.startsWith('beard')) return 'beard';
  if (name.startsWith('head')) return 'head';
  return null;
}

const ITEMS = {
  eyes: Object.keys(ALIGNMENT.eyes),
  hair: Object.keys(ALIGNMENT.hair),
  beard: Object.keys(ALIGNMENT.beard)
};

const ORIGINAL_ALIGNMENT = JSON.parse(JSON.stringify(ALIGNMENT));

async function loadAllFiles() {
  try {
    const configResp = await adminFetch('/admin/api/builder/config');
    if (configResp.ok) {
      const configText = await configResp.text();
      function extractObj(name, text) {
        const start = text.indexOf('export const ' + name + ' = ');
        if (start === -1) return null;
        const objStart = text.indexOf('{', start);
        let braceCount = 0;
        for (let i = objStart; i < text.length; i++) {
          if (text[i] === '{') braceCount++;
          if (text[i] === '}') braceCount--;
          if (braceCount === 0) {
            try { return eval('(' + text.substring(objStart, i + 1) + ')'); } catch { return null; }
          }
        }
        return null;
      }
      const gameAlignment = extractObj('AVATAR_ALIGNMENT', configText);
      const gameItems = extractObj('AVATAR_ITEMS', configText);

      if (gameAlignment) {
        for (const layer of ['eyes', 'hair', 'beard']) {
          if (!gameAlignment[layer]) continue;
          for (const [file, pos] of Object.entries(gameAlignment[layer])) {
            ALIGNMENT[layer][file] = pos;
            if (!ITEMS[layer].includes(file)) ITEMS[layer].push(file);
          }
          ORIGINAL_ALIGNMENT[layer] = { ...ALIGNMENT[layer] };
        }
      }
      if (gameItems) {
        for (const layer of ['eyes', 'hair', 'beard']) {
          if (!gameItems[layer]) continue;
          for (const file of gameItems[layer]) {
            if (!ITEMS[layer].includes(file)) ITEMS[layer].push(file);
            if (!ALIGNMENT[layer][file]) ALIGNMENT[layer][file] = { scale: 100, x: 0, y: 0 };
          }
        }
      }
    }

    const resp = await adminFetch('/admin/api/builder/files');
    if (!resp.ok) return;
    const files = await resp.json();

    for (const f of files) {
      const layer = classifyFile(f);
      if (!layer || layer === 'head') continue;
      if (!ITEMS[layer].includes(f)) ITEMS[layer].push(f);
      if (!ALIGNMENT[layer][f]) ALIGNMENT[layer][f] = { scale: 100, x: 0, y: 0 };
    }

    for (const f of files) {
      if (f.startsWith('head') && !HEADS.includes(f)) HEADS.push(f);
    }
  } catch {}
}

// ── STATE ──
const state = {
  head: 0,
  activeLayer: 'eyes',
  selectedDupe: -1,
  dragging: null,
  genderFilter: 'all',
  active: { eyes: null, hair: null, beard: null },
  positions: {},
};

let GENDER_MAP = {};
let PAID_MAP = {};
let COSTUMES = [];
let paidMode = 'elements';

try {
  localStorage.removeItem('avatarEditorState');
  const s = JSON.parse(localStorage.getItem('avatarEditorState2'));
  if (s) {
    if (typeof s.head === 'number') state.head = s.head;
    if (s.positions && typeof s.positions === 'object') {
      for (const key of Object.keys(s.positions)) {
        const p = s.positions[key];
        if (p && typeof p === 'object') {
          state.positions[key] = {
            x: typeof p.x === 'number' ? p.x : 0,
            y: typeof p.y === 'number' ? p.y : 0,
            scale: typeof p.scale === 'number' ? p.scale : 100,
            dupes: Array.isArray(p.dupes) ? p.dupes : []
          };
        }
      }
    }
    if (s.active && typeof s.active === 'object') {
      for (const l of ['eyes','hair','beard']) {
        if (s.active[l] && typeof s.active[l] === 'string') state.active[l] = s.active[l];
      }
    }
    if (s.genderMap && typeof s.genderMap === 'object') GENDER_MAP = s.genderMap;
    if (s.genderFilter && typeof s.genderFilter === 'string') state.genderFilter = s.genderFilter;
    if (s.paidMap && typeof s.paidMap === 'object') PAID_MAP = s.paidMap;
    if (Array.isArray(s.costumes)) COSTUMES = s.costumes;
  }
} catch(e) {
  localStorage.removeItem('avatarEditorState2');
}

function saveState() {
  localStorage.setItem('avatarEditorState2', JSON.stringify({
    head: state.head,
    active: state.active,
    positions: state.positions,
    genderMap: GENDER_MAP,
    genderFilter: state.genderFilter,
    paidMap: PAID_MAP,
    costumes: COSTUMES,
  }));
}

function getPos(layer) {
  const file = state.active[layer];
  if (!file) return { x: 0, y: 0, scale: 100, dupes: [] };
  const key = layer + '.' + file;
  const saved = state.positions[key];
  if (saved) {
    return {
      x: saved.x ?? 0, y: saved.y ?? 0, scale: saved.scale ?? 100,
      dupes: Array.isArray(saved.dupes) ? saved.dupes : []
    };
  }
  const al = ALIGNMENT[layer]?.[file];
  return al ? { ...al, dupes: [] } : { scale: 100, x: 0, y: 0, dupes: [] };
}

function setPos(layer, updates) {
  const file = state.active[layer];
  if (!file) return;
  const key = layer + '.' + file;
  state.positions[key] = { ...getPos(layer), ...updates };
  saveState();
}

async function autoSaveCurrent() {
  const { alignment, items } = buildGameConfig();
  try {
    await adminFetch('/admin/api/builder/sync', {
      method: 'POST',
      body: JSON.stringify({ alignment, items })
    });
  } catch {}
}

// ── RENDERING ──
const canvas = document.getElementById('canvas');
let layerElements = {};

async function init() {
  renderHeads();
  renderGenderTabs();
  renderLayerTabs();
  renderItems();
  renderCanvas();
  renderPreviews();
  updateConfigPreview();
  setupDrag();
  setupControls();
  setupUpload();
  setupActions();
  setupPaid();
  renderCostumes();
  updatePaidElementControls();

  await loadAllFiles();
  renderHeads();
  renderItems();
  renderCanvas();
  renderPreviews();
  updateConfigPreview();
}

function renderHeads() {
  const grid = document.getElementById('headGrid');
  grid.innerHTML = '';
  HEADS.forEach((f, i) => {
    const el = document.createElement('div');
    el.className = 'item-thumb' + (i === state.head ? ' active' : '');
    el.innerHTML = `<img src="${resolveSrc(f)}" alt="">`;
    el.addEventListener('click', () => {
      state.head = i;
      renderHeads();
      renderCanvas();
      renderPreviews();
      saveState();
    });
    grid.appendChild(el);
  });
}

function renderGenderTabs() {
  document.querySelectorAll('.gender-tab').forEach(tab => {
    tab.classList.toggle('active', tab.dataset.gender === state.genderFilter);
  });
}

document.querySelectorAll('.gender-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    state.genderFilter = tab.dataset.gender;
    document.querySelectorAll('.gender-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    renderItems();
    saveState();
  });
});

function renderLayerTabs() {
  document.querySelectorAll('.layer-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.layer-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      state.activeLayer = tab.dataset.layer;
      state.selectedDupe = -1;
      renderItems();
      renderPosControls();
      renderCanvas();
    });
  });
}

function renderItems() {
  const grid = document.getElementById('itemGrid');
  const layer = state.activeLayer;
  grid.innerHTML = '';

  ITEMS[layer].forEach(f => {
    const gender = GENDER_MAP[f];
    if (state.genderFilter !== 'all' && gender && gender !== state.genderFilter) return;

    const isActive = state.active[layer] === f;
    const pos = getPos(layer);
    const dupeCount = isActive ? (pos.dupes || []).length : 0;
    const el = document.createElement('div');
    el.className = 'item-thumb' + (isActive ? ' active' : '');

    const genderClass = gender || 'untagged';
    const genderLabel = gender === 'male' ? 'M' : gender === 'female' ? 'F' : '?';
    const paidPrice = PAID_MAP[f];
    const isCostumePart = COSTUMES.some(c =>
      (c.items.eyes === f) || (c.items.hair === f) || (c.items.beard === f)
    );
    el.innerHTML = `<img src="${resolveSrc(f)}" alt="">` +
      (paidPrice ? `<span class="paid-badge">$${paidPrice}</span>` : '') +
      (isCostumePart && !paidPrice ? `<span class="paid-badge costume-badge">C</span>` : '') +
      `<span class="gender-badge ${genderClass}" data-file="${f}">${genderLabel}</span>` +
      (dupeCount > 0 ? `<span class="dupe-badge">${dupeCount}D</span>` : '');

    el.querySelector('.gender-badge').addEventListener('click', (e) => {
      e.stopPropagation();
      const current = GENDER_MAP[f];
      if (!current || current === 'female') {
        delete GENDER_MAP[f];
      } else if (current === 'male') {
        GENDER_MAP[f] = 'female';
      }
      if (!GENDER_MAP[f] && !current) {
        GENDER_MAP[f] = 'male';
      }
      saveState();
      renderItems();
    });

    el.addEventListener('click', () => selectItem(layer, f));
    grid.appendChild(el);
  });
}

function selectItem(layer, file) {
  if (state.active[layer] && state.active[layer] !== file) autoSaveCurrent();
  if (state.active[layer] === file) {
    state.active[layer] = null;
    state.selectedDupe = -1;
  } else {
    state.active[layer] = file;
    state.selectedDupe = -1;
  }
  renderItems();
  renderPosControls();
  renderDupes();
  renderCanvas();
  renderPreviews();
  updateConfigPreview();
  updatePaidElementControls();
  saveState();
}

function renderPosControls() {
  const sec = document.getElementById('posSection');
  const layer = state.activeLayer;
  const file = state.active[layer];
  if (!file) { sec.style.display = 'none'; return; }
  sec.style.display = 'block';

  const pos = getPos(layer);
  const target = state.selectedDupe >= 0 && Array.isArray(pos.dupes) && pos.dupes[state.selectedDupe]
    ? pos.dupes[state.selectedDupe] : pos;
  document.getElementById('posX').value = target.x ?? 0;
  document.getElementById('posXRange').value = target.x ?? 0;
  document.getElementById('posY').value = target.y ?? 0;
  document.getElementById('posYRange').value = target.y ?? 0;
  document.getElementById('posScale').value = target.scale ?? 100;
  document.getElementById('posScaleRange').value = target.scale ?? 100;
}

function renderDupes() {
  const list = document.getElementById('dupesList');
  list.innerHTML = '';
  const layer = state.activeLayer;
  const file = state.active[layer];
  if (!file) return;
  const pos = getPos(layer);
  if (!pos || !Array.isArray(pos.dupes) || pos.dupes.length === 0) return;
  list.innerHTML = '<div style="font-size:12px;color:#8b6914;margin-bottom:4px;">Duplicates (click to select):</div>';
  (pos.dupes || []).forEach((dupe, i) => {
    const item = document.createElement('div');
    item.className = 'dupe-item' + (state.selectedDupe === i ? ' selected' : '');
    item.style.background = state.selectedDupe === i ? '#3a2010' : '#1a0e08';
    item.innerHTML = `
      <div class="dupe-mini"><img src="${resolveSrc(file)}" alt=""></div>
      <div class="dupe-info">#${i + 1} — x:${dupe.x} y:${dupe.y} s:${dupe.scale}%</div>
      <button class="dupe-remove" data-idx="${i}">&times;</button>
    `;
    item.addEventListener('click', (e) => {
      if (e.target.classList.contains('dupe-remove')) return;
      state.selectedDupe = i;
      renderPosControls();
      renderDupes();
      renderCanvas();
    });
    item.querySelector('.dupe-remove').addEventListener('click', () => {
      const dupes = pos.dupes || [];
      dupes.splice(i, 1);
      setPos(layer, { dupes });
      if (state.selectedDupe >= dupes.length) state.selectedDupe = -1;
      if (state.selectedDupe === i) state.selectedDupe = -1;
      renderItems();
      renderDupes();
      renderCanvas();
      renderPreviews();
      updateConfigPreview();
    });
    list.appendChild(item);
  });
}

function renderCanvas() {
  canvas.querySelectorAll('.layer-img').forEach(el => el.remove());

  const headImg = document.createElement('img');
  headImg.className = 'layer-img';
  headImg.src = resolveSrc(HEADS[state.head]);
  headImg.style.zIndex = '1';
  canvas.appendChild(headImg);

  const layer = state.activeLayer;
  const file = state.active[layer];
  if (!file) return;

  const pos = getPos(layer);
  let zIdx = 2;
  const mainImg = createLayerImg(file, pos.x, pos.y, pos.scale, zIdx++, state.selectedDupe === -1);
  canvas.appendChild(mainImg);

  (pos.dupes || []).forEach((dupe, i) => {
    const dupeImg = createLayerImg(file, dupe.x, dupe.y, dupe.scale, zIdx++, state.selectedDupe === i);
    canvas.appendChild(dupeImg);
  });
}

function createLayerImg(file, x, y, scale, zIdx, highlight) {
  const img = document.createElement('img');
  img.className = 'layer-img';
  img.src = resolveSrc(file);
  img.style.zIndex = zIdx;
  const ratio = CANVAS_DISPLAY / TOOL_SIZE;
  img.style.transform = `translate(${x * ratio}px, ${y * ratio}px) scale(${scale / 100})`;
  if (highlight) img.style.filter = 'drop-shadow(0 0 3px rgba(255,107,0,0.6))';
  return img;
}

function renderPreviews() {
  [40, 56, 80, 120].forEach(size => {
    const box = document.getElementById(`prev${size}`);
    box.innerHTML = '';
    const ratio = size / TOOL_SIZE;

    const img = document.createElement('img');
    img.src = resolveSrc(HEADS[state.head]);
    box.appendChild(img);

    const order = ['beard', 'eyes', 'hair'];
    for (const layerName of order) {
      const file = state.active[layerName];
      if (!file) continue;
      const pos = getPos(layerName);
      addPreviewLayer(box, file, pos.x, pos.y, pos.scale, ratio);
      (pos.dupes || []).forEach(dupe => {
        addPreviewLayer(box, file, dupe.x, dupe.y, dupe.scale, ratio);
      });
    }
  });
}

function addPreviewLayer(container, file, x, y, scale, ratio) {
  const img = document.createElement('img');
  img.src = resolveSrc(file);
  img.style.transform = `translate(${x * ratio}px, ${y * ratio}px) scale(${scale / 100})`;
  container.appendChild(img);
}

// ── DRAG SYSTEM ──
function setupDrag() {
  canvas.addEventListener('mousedown', onDragStart);
  canvas.addEventListener('touchstart', onDragStart, { passive: false });
  document.addEventListener('mousemove', onDragMove);
  document.addEventListener('touchmove', onDragMove, { passive: false });
  document.addEventListener('mouseup', onDragEnd);
  document.addEventListener('touchend', onDragEnd);
}

function getEventPos(e) {
  if (e.touches && e.touches[0]) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
  return { x: e.clientX, y: e.clientY };
}

function onDragStart(e) {
  const layer = state.activeLayer;
  const file = state.active[layer];
  if (!file) return;
  e.preventDefault();
  const pos = getPos(layer);
  const target = state.selectedDupe >= 0 ? pos.dupes[state.selectedDupe] : pos;
  const clientPos = getEventPos(e);
  state.dragging = { startX: clientPos.x, startY: clientPos.y, origX: target.x, origY: target.y };
}

function onDragMove(e) {
  if (!state.dragging) return;
  e.preventDefault();
  const clientPos = getEventPos(e);
  const dx = clientPos.x - state.dragging.startX;
  const dy = clientPos.y - state.dragging.startY;
  const toolDx = Math.round(dx * PIXEL_TO_TOOL);
  const toolDy = Math.round(dy * PIXEL_TO_TOOL);
  const newX = state.dragging.origX + toolDx;
  const newY = state.dragging.origY + toolDy;
  const layer = state.activeLayer;
  if (state.selectedDupe >= 0) {
    const pos = getPos(layer);
    const dupes = [...(pos.dupes || [])];
    dupes[state.selectedDupe] = { ...dupes[state.selectedDupe], x: newX, y: newY };
    setPos(layer, { dupes });
  } else {
    setPos(layer, { x: newX, y: newY });
  }
  renderPosControls();
  renderCanvas();
  renderPreviews();
  updateConfigPreview();
}

function onDragEnd() {
  if (state.dragging) { state.dragging = null; saveState(); }
}

// ── CONTROLS ──
function setupControls() {
  const bind = (inputId, rangeId, prop) => {
    const input = document.getElementById(inputId);
    const range = document.getElementById(rangeId);
    const update = (val) => {
      const layer = state.activeLayer;
      const file = state.active[layer];
      if (!file) return;
      const numVal = parseFloat(val);
      if (state.selectedDupe >= 0) {
        const pos = getPos(layer);
        const dupes = [...(pos.dupes || [])];
        dupes[state.selectedDupe] = { ...dupes[state.selectedDupe], [prop]: numVal };
        setPos(layer, { dupes });
      } else {
        setPos(layer, { [prop]: numVal });
      }
      input.value = val;
      range.value = val;
      renderCanvas();
      renderPreviews();
      renderDupes();
      updateConfigPreview();
    };
    input.addEventListener('input', () => update(input.value));
    range.addEventListener('input', () => update(range.value));
  };

  bind('posX', 'posXRange', 'x');
  bind('posY', 'posYRange', 'y');
  bind('posScale', 'posScaleRange', 'scale');

  document.getElementById('duplicateBtn').addEventListener('click', () => {
    const layer = state.activeLayer;
    const file = state.active[layer];
    if (!file) return;
    const pos = getPos(layer);
    const dupes = pos.dupes || [];
    const source = state.selectedDupe >= 0 && dupes[state.selectedDupe] ? dupes[state.selectedDupe] : pos;
    dupes.push({ x: (source.x || 0) + 10, y: (source.y || 0) + 10, scale: source.scale || 100 });
    setPos(layer, { dupes });
    state.selectedDupe = dupes.length - 1;
    renderItems();
    renderPosControls();
    renderDupes();
    renderCanvas();
    renderPreviews();
    updateConfigPreview();
    showToast('Duplicated! Drag independently.');
  });

  document.getElementById('resetPosBtn').addEventListener('click', () => {
    const layer = state.activeLayer;
    const file = state.active[layer];
    if (!file) return;
    const pos = getPos(layer);
    const target = state.selectedDupe >= 0 ? pos.dupes[state.selectedDupe] : pos;
    const gameAl = ALIGNMENT[layer]?.[file];
    target.x = gameAl ? gameAl.x : 0;
    target.y = gameAl ? gameAl.y : 0;
    target.scale = gameAl ? gameAl.scale : 100;
    if (state.selectedDupe < 0) setPos(layer, { x: target.x, y: target.y, scale: target.scale });
    renderPosControls();
    renderDupes();
    renderCanvas();
    renderPreviews();
    updateConfigPreview();
    showToast(gameAl ? 'Reset to game position' : 'Reset to defaults (0, 0, 100%)');
  });

  document.getElementById('removeBtn').addEventListener('click', async () => {
    const layer = state.activeLayer;
    const file = state.active[layer];
    if (!file) return;

    if (state.selectedDupe >= 0) {
      const pos = getPos(layer);
      const dupes = pos.dupes || [];
      dupes.splice(state.selectedDupe, 1);
      setPos(layer, { dupes });
      state.selectedDupe = -1;
      renderItems();
      renderPosControls();
      renderDupes();
      renderCanvas();
      renderPreviews();
      updateConfigPreview();
      saveState();
      return;
    }

    const fileName = file;
    if (ITEMS[layer]) ITEMS[layer] = ITEMS[layer].filter(f => f !== fileName && !f.startsWith(fileName.replace('.png', '') + '_dup'));
    if (ALIGNMENT[layer]) {
      delete ALIGNMENT[layer][fileName];
      const baseName = fileName.replace('.png', '');
      for (const key of Object.keys(ALIGNMENT[layer])) {
        if (key.startsWith(baseName + '_dup')) delete ALIGNMENT[layer][key];
      }
    }
    if (ORIGINAL_ALIGNMENT[layer]) {
      delete ORIGINAL_ALIGNMENT[layer][fileName];
      const baseName = fileName.replace('.png', '');
      for (const key of Object.keys(ORIGINAL_ALIGNMENT[layer])) {
        if (key.startsWith(baseName + '_dup')) delete ORIGINAL_ALIGNMENT[layer][key];
      }
    }
    const key = layer + '.' + fileName;
    delete state.positions[key];
    const baseName = fileName.replace('.png', '');
    for (const posKey of Object.keys(state.positions)) {
      if (posKey.startsWith(layer + '.' + baseName + '_dup')) delete state.positions[posKey];
    }
    if (window._uploadedAssets) delete window._uploadedAssets[fileName];
    delete GENDER_MAP[fileName];
    delete PAID_MAP[fileName];
    state.active[layer] = null;
    state.selectedDupe = -1;

    try {
      const resp = await adminFetch('/admin/api/builder/remove', {
        method: 'POST',
        body: JSON.stringify({ filename: fileName, layer })
      });
      const data = await resp.json();
      if (resp.ok) showToast(`Removed ${fileName} from builder and game`);
      else showToast(`Removed from builder, but game removal failed: ${data.error}`);
    } catch {
      showToast(`Removed from builder (server error)`);
    }

    renderItems();
    renderPosControls();
    renderDupes();
    renderCanvas();
    renderPreviews();
    updateConfigPreview();
    saveState();
  });
}

// ── UPLOAD ──
function setupUpload() {
  const input = document.getElementById('uploadInput');
  const btn = document.getElementById('uploadBtn');

  btn.addEventListener('click', () => input.click());

  input.addEventListener('change', async () => {
    const file = input.files[0];
    if (!file) return;
    input.value = '';

    const layer = state.activeLayer;
    const safeName = file.name.replace(/[^a-zA-Z0-9._-]/g, '_').toLowerCase();
    const fileName = safeName.endsWith('.png') ? safeName : safeName.replace(/\.\w+$/, '.png');

    const dataUrl = await new Promise(resolve => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.readAsDataURL(file);
    });

    try {
      const resp = await adminFetch('/admin/api/builder/upload', {
        method: 'POST',
        body: JSON.stringify({ filename: fileName, data: dataUrl, layer })
      });
      const data = await resp.json();
      if (!resp.ok) throw new Error(data.error || 'Upload failed');
      showToast(`Uploaded ${fileName} to game!`);
    } catch {
      showToast('Upload failed');
    }

    const defaultPos = { scale: 100, x: 0, y: 0 };
    if (!ALIGNMENT[layer]) ALIGNMENT[layer] = {};
    ALIGNMENT[layer][fileName] = defaultPos;
    if (!ITEMS[layer]) ITEMS[layer] = [];
    if (!ITEMS[layer].includes(fileName)) ITEMS[layer].push(fileName);
    if (!window._uploadedAssets) window._uploadedAssets = {};
    window._uploadedAssets[fileName] = dataUrl;
    state.active[layer] = fileName;
    state.selectedDupe = -1;

    renderItems();
    renderPosControls();
    renderDupes();
    renderCanvas();
    renderPreviews();
    updateConfigPreview();
    saveState();
  });
}

function resolveSrc(file) {
  if (window._uploadedAssets && window._uploadedAssets[file]) return window._uploadedAssets[file];
  return BASE + file;
}

// ── ACTIONS ──
function setupActions() {
  document.getElementById('saveBtn').addEventListener('click', async () => {
    const { alignment, items, gender, paid, costumes } = buildGameConfig();
    const statusEl = document.getElementById('saveStatus');
    statusEl.textContent = 'Syncing...';

    try {
      const resp = await adminFetch('/admin/api/builder/sync', {
        method: 'POST',
        body: JSON.stringify({ alignment, items, gender, paid, costumes })
      });
      const data = await resp.json();
      if (resp.ok) {
        showToast('Saved to game! Vite will hot-reload automatically.');
        statusEl.textContent = 'Synced to: ' + data.path;
      } else {
        throw new Error(data.error || 'Sync failed');
      }
    } catch (err) {
      statusEl.textContent = 'Sync failed: ' + (err.message || 'unknown error');
      const { alignment } = buildGameConfig();
      try {
        await navigator.clipboard.writeText(JSON.stringify(alignment, null, 2));
        showToast('Config copied to clipboard!');
      } catch {
        showToast('Save failed. Check server logs.');
      }
    }
  });

  document.getElementById('copyBtn').addEventListener('click', async () => {
    const { alignment } = buildGameConfig();
    try {
      await navigator.clipboard.writeText(JSON.stringify(alignment, null, 2));
      showToast('Config copied to clipboard!');
    } catch { showToast('Copy failed'); }
  });

  document.getElementById('exportBtn').addEventListener('click', () => {
    const { alignment } = buildGameConfig();
    const blob = new Blob([JSON.stringify(alignment, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'avatar-alignment.json';
    a.click();
    URL.revokeObjectURL(url);
    showToast('JSON file downloaded!');
  });
}

function buildGameConfig() {
  const alignment = {};
  const items = { eyes: [], hair: [], beard: [] };
  const gender = { eyes: {}, hair: {}, beard: {} };
  const paid = {};

  for (const layerName of ['eyes', 'hair', 'beard']) {
    alignment[layerName] = {};
    for (const file of ITEMS[layerName]) {
      const key = layerName + '.' + file;
      const custom = state.positions[key];
      const originalAl = ORIGINAL_ALIGNMENT[layerName]?.[file];

      if (custom) {
        alignment[layerName][file] = { scale: custom.scale, x: custom.x, y: custom.y };
        items[layerName].push(file);
        if (custom.dupes && Array.isArray(custom.dupes)) {
          custom.dupes.forEach((dupe, i) => {
            const baseName = file.replace('.png', '');
            const dupeName = `${baseName}_dup${i + 1}.png`;
            alignment[layerName][dupeName] = { scale: dupe.scale, x: dupe.x, y: dupe.y };
            items[layerName].push(dupeName);
          });
        }
      } else if (originalAl) {
        alignment[layerName][file] = { ...originalAl };
        items[layerName].push(file);
      }
      if (GENDER_MAP[file]) gender[layerName][file] = GENDER_MAP[file];
      if (PAID_MAP[file]) paid[file] = PAID_MAP[file];
    }
  }

  return { alignment, items, gender, paid, costumes: COSTUMES };
}

function buildConfig() {
  const { alignment } = buildGameConfig();
  return alignment;
}

function updateConfigPreview() {
  const { alignment, paid, costumes } = buildGameConfig();
  const preview = { ...alignment };
  if (Object.keys(paid).length > 0) preview._paid = paid;
  if (costumes.length > 0) preview._costumes = costumes;
  document.getElementById('configPreview').textContent = JSON.stringify(preview, null, 2);
}

// ── PAID ELEMENT CONTROLS ──
function updatePaidElementControls() {
  const layer = state.activeLayer;
  const file = state.active[layer];
  const info = document.getElementById('paidElementInfo');
  const controls = document.getElementById('paidElementControls');

  if (!file) {
    info.style.display = 'block';
    controls.style.display = 'none';
    info.textContent = 'Select an item to set its price';
    return;
  }

  info.style.display = 'none';
  controls.style.display = 'block';
  document.getElementById('paidPrice').value = PAID_MAP[file] || 0;
}

function setupPaid() {
  document.querySelectorAll('.paid-mode-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      paidMode = tab.dataset.mode;
      document.querySelectorAll('.paid-mode-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById('paidElements').style.display = paidMode === 'elements' ? 'block' : 'none';
      document.getElementById('paidCostume').style.display = paidMode === 'costume' ? 'block' : 'none';
    });
  });

  document.getElementById('setPaidBtn').addEventListener('click', () => {
    const layer = state.activeLayer;
    const file = state.active[layer];
    if (!file) return;
    const price = parseFloat(document.getElementById('paidPrice').value);
    if (!price || price <= 0) { showToast('Enter a price greater than 0'); return; }
    PAID_MAP[file] = price;
    saveState();
    renderItems();
    updateConfigPreview();
    showToast(`${file} set as paid ($${price})`);
  });

  document.getElementById('setFreeBtn').addEventListener('click', () => {
    const layer = state.activeLayer;
    const file = state.active[layer];
    if (!file) return;
    delete PAID_MAP[file];
    saveState();
    renderItems();
    updatePaidElementControls();
    updateConfigPreview();
    showToast(`${file} set as free`);
  });

  document.getElementById('createCostumeBtn').addEventListener('click', () => {
    const name = document.getElementById('costumeName').value.trim();
    const price = parseFloat(document.getElementById('costumePrice').value);
    if (!name) { showToast('Enter a costume name'); return; }
    if (!price || price <= 0) { showToast('Enter a price greater than 0'); return; }
    const hasAny = state.active.eyes || state.active.hair || state.active.beard;
    if (!hasAny) { showToast('Select at least one accessory to create a costume'); return; }

    COSTUMES.push({
      name, price,
      head: HEADS[state.head],
      items: { eyes: state.active.eyes || null, hair: state.active.hair || null, beard: state.active.beard || null }
    });
    document.getElementById('costumeName').value = '';
    saveState();
    renderCostumes();
    renderItems();
    updateConfigPreview();
    showToast(`Costume "${name}" created ($${price})`);
  });
}

function renderCostumes() {
  const list = document.getElementById('costumeList');
  list.innerHTML = '';
  if (COSTUMES.length === 0) return;

  COSTUMES.forEach((costume, i) => {
    const item = document.createElement('div');
    item.className = 'costume-item';
    const layers = [];
    if (costume.items.eyes) layers.push('eyes');
    if (costume.items.hair) layers.push('hair');
    if (costume.items.beard) layers.push('beard');

    item.innerHTML = `
      <div class="costume-preview" id="costumePrev${i}"></div>
      <div class="costume-info">
        <div class="costume-name">${costume.name}</div>
        <div class="costume-price">$${costume.price}</div>
        <div class="costume-layers">${layers.join(', ')} | head: ${costume.head || 'default'}</div>
      </div>
      <button class="costume-remove" data-idx="${i}">&times;</button>
    `;

    item.querySelector('.costume-remove').addEventListener('click', (e) => {
      e.stopPropagation();
      COSTUMES.splice(i, 1);
      saveState();
      renderCostumes();
      renderItems();
      updateConfigPreview();
      showToast('Costume removed');
    });

    list.appendChild(item);
    renderCostumePreview(i, costume);
  });
}

function renderCostumePreview(index, costume) {
  const box = document.getElementById(`costumePrev${index}`);
  if (!box) return;
  const ratio = 36 / TOOL_SIZE;
  if (costume.head) {
    const img = document.createElement('img');
    img.src = resolveSrc(costume.head);
    box.appendChild(img);
  }
  const order = ['beard', 'eyes', 'hair'];
  for (const layerName of order) {
    const file = costume.items[layerName];
    if (!file) continue;
    const pos = getPos(layerName);
    addPreviewLayer(box, file, pos.x, pos.y, pos.scale, ratio);
  }
}

// ── TOAST ──
function showToast(msg) {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2500);
}

// ── PROMO CODES (session-authed via Laravel admin API) ──
let promoRewardType = 'element';

function setupPromoCodes() {
  document.querySelectorAll('.promo-type-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      promoRewardType = tab.dataset.type;
      document.querySelectorAll('.promo-type-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById('promoElementSelect').style.display = promoRewardType === 'element' ? 'block' : 'none';
      document.getElementById('promoCostumeSelect').style.display = promoRewardType === 'costume' ? 'block' : 'none';
      document.getElementById('promoCreditsSelect').style.display = promoRewardType === 'credits' ? 'block' : 'none';
    });
  });

  populatePromoPickers();
  document.getElementById('generatePromoBtn').addEventListener('click', generatePromoCode);
  loadPromoCodes();
}

function populatePromoPickers() {
  const elementPicker = document.getElementById('promoElementPicker');
  elementPicker.innerHTML = '<option value="">-- Pick a paid element --</option>';
  for (const [file, price] of Object.entries(PAID_MAP)) {
    const opt = document.createElement('option');
    opt.value = file;
    opt.textContent = `${file} ($${price})`;
    elementPicker.appendChild(opt);
  }

  const costumePicker = document.getElementById('promoCostumePicker');
  costumePicker.innerHTML = '<option value="">-- Pick a costume --</option>';
  COSTUMES.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id || c.name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
    opt.textContent = `${c.name} ($${c.price})`;
    costumePicker.appendChild(opt);
  });
}

async function generatePromoCode() {
  let rewardType = promoRewardType;
  let rewardId = '';

  if (rewardType === 'element') {
    rewardId = document.getElementById('promoElementPicker').value;
    if (!rewardId) { showToast('Select an element first'); return; }
  } else if (rewardType === 'costume') {
    rewardId = document.getElementById('promoCostumePicker').value;
    if (!rewardId) { showToast('Select a costume first'); return; }
  } else {
    rewardId = document.getElementById('promoCreditsAmount').value;
    if (!rewardId || parseInt(rewardId) <= 0) { showToast('Enter a valid credit amount'); return; }
  }

  const maxUses = document.getElementById('promoMaxUses').value || null;
  const expiresAt = document.getElementById('promoExpires').value || null;
  const customCode = document.getElementById('promoCustomCode').value.trim() || null;

  try {
    const resp = await adminFetch('/admin/api/builder/promo-codes', {
      method: 'POST',
      body: JSON.stringify({
        reward_type: rewardType,
        reward_id: rewardId,
        max_uses: maxUses ? parseInt(maxUses) : null,
        expires_at: expiresAt || null,
        custom_code: customCode,
      }),
    });

    if (!resp.ok) {
      let errMsg = 'Failed';
      try { const err = await resp.json(); errMsg = err.message || err.error || errMsg; } catch {}
      throw new Error(errMsg);
    }

    const data = await resp.json();
    showToast(`Code generated: ${data.code}`);
    loadPromoCodes();
  } catch (err) {
    showToast('Error: ' + (err.message || 'Failed to generate code'));
  }
}

async function loadPromoCodes() {
  try {
    const resp = await adminFetch('/admin/api/builder/promo-codes');
    if (!resp.ok) return;
    const codes = await resp.json();
    renderPromoCodes(codes);
  } catch {}
}

function renderPromoCodes(codes) {
  const list = document.getElementById('promoCodesList');
  list.innerHTML = '';

  if (!codes || codes.length === 0) {
    list.innerHTML = '<div style="font-size:11px;color:#8b6914;text-align:center;">No promo codes yet</div>';
    return;
  }

  codes.forEach(code => {
    const row = document.createElement('div');
    row.className = 'promo-code-row';
    const rewardLabel = code.reward_type === 'credits' ? `${code.reward_id} credits` : code.reward_id;
    const statusBadge = code.is_expired
      ? '<span style="color:#ef4444;">[EXPIRED]</span>'
      : code.is_fully_used
        ? '<span style="color:#f59e0b;">[USED UP]</span>'
        : '<span style="color:#10b981;">[ACTIVE]</span>';

    row.innerHTML = `
      <div class="promo-code-text">${code.code}</div>
      <div class="promo-code-meta">
        ${statusBadge} ${rewardLabel}
        <br>${code.uses_count}/${code.max_uses || '∞'} uses
        ${code.expires_at ? '<br>Exp: ' + new Date(code.expires_at).toLocaleDateString() : ''}
      </div>
      <button class="promo-delete-btn" data-id="${code.id}">&times;</button>
    `;

    row.querySelector('.promo-delete-btn').addEventListener('click', async () => {
      try {
        await adminFetch('/admin/api/builder/promo-codes/delete', {
          method: 'POST',
          body: JSON.stringify({ id: code.id }),
        });
        showToast('Code deleted');
        loadPromoCodes();
      } catch { showToast('Delete failed'); }
    });

    list.appendChild(row);
  });
}

init();
setupPromoCodes();
</script>
</body>
</html>
