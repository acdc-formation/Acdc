(function(){
  function root(){ return document.documentElement; }
  function setVar(name,val){ if(!name || val===null) return; root().style.setProperty(name, val); var preview=document.getElementById('acdc-ui-preview'); if(preview){ preview.style.setProperty(name, val); } }
  function pxVars(){ return ['width','height','size','radius','padding','row-height']; }
  function normalizeValue(input){
    const cssVar=input.getAttribute('data-css-var');
    if(!cssVar) return [null,null];
    let v=(input.value||'').trim();
    if(input.type==='number'){
      if(v==='') return [cssVar,null];
      if(pxVars().some(k=>cssVar.indexOf(k)>-1)) v=v+'px';
    }
    return [cssVar,v];
  }
  function updateSpecial(input){
    const target=input.getAttribute('data-preview-target');
    const textTarget=input.getAttribute('data-preview-text');
    const value=input.value || '';
    if(textTarget){
      const map={display_name:'acdc-preview-display-name',field_error_message:'acdc-preview-error-message'};
      const el=document.getElementById(map[textTarget]||('acdc-preview-'+textTarget.replace(/_/g,'-')));
      if(el) el.textContent=value;
      return;
    }
    const preview=document.getElementById('acdc-ui-preview');
    if(!preview) return;
    if(target==='logo'){
      const img=document.getElementById('acdc-preview-logo'); if(img){ img.src=value; img.style.display=value ? '' : 'none'; }
    }
    if(target==='favicon'){
      const img=document.getElementById('acdc-preview-favicon'); if(img){ img.src=value; img.style.display=value ? '' : 'none'; }
    }
    if(target==='button_style_primary'){ const el=document.getElementById('acdc-preview-btn-primary'); if(el){ el.dataset.style=value; el.classList.toggle('is-outline', value==='outline'); el.classList.toggle('is-solid', value==='solid'); } }
    if(target==='button_style_secondary'){ const el=document.getElementById('acdc-preview-btn-secondary'); if(el){ el.dataset.style=value; el.classList.toggle('is-outline', value==='outline'); el.classList.toggle('is-solid', value==='solid'); } }
    if(target==='button_style_danger'){ const el=document.getElementById('acdc-preview-btn-danger'); if(el){ el.dataset.style=value; el.classList.toggle('is-solid', value==='solid'); } }
    if(target==='actions_column_style'){ const el=document.getElementById('acdc-preview-actions'); if(el){ el.classList.toggle('is-pills', value==='pills'); } }
    if(target==='icon_style'){ const el=document.getElementById('acdc-preview-actions'); if(el){ el.classList.toggle('is-filled', value==='filled'); } }
    if(target==='menu_dots_style'){ const el=document.getElementById('acdc-preview-dots'); if(el){ el.classList.toggle('is-outline', value==='outline'); el.classList.toggle('is-filled', value==='filled'); } }
    if(target==='table_density'){ const table=document.querySelector('.acdc-ui-preview-table'); if(table){ table.dataset.density=value; } }
    if(target==='checkbox_style' || target==='radio_style' || target==='select_style' || target==='button_hover_mode') return;
  }
  function bindPreview(){
    document.querySelectorAll('.acdc-ui-admin-page [data-css-var], .acdc-ui-admin-page [data-preview-target], .acdc-ui-admin-page [data-preview-text]').forEach(function(input){
      const evt=(input.tagName==='SELECT') ? 'change' : 'input';
      input.addEventListener(evt, function(){ const pair=normalizeValue(input); if(pair[0]) setVar(pair[0], pair[1]); updateSpecial(input); });
      const pair=normalizeValue(input); if(pair[0]) setVar(pair[0], pair[1]); updateSpecial(input);
    });
  }
  function bindTabs(){
    const tabs=document.querySelectorAll('.acdc-ui-subtab');
    const panels=document.querySelectorAll('[data-ui-panel]');
    tabs.forEach(function(btn){
      btn.addEventListener('click', function(){
        const slug=btn.getAttribute('data-ui-tab');
        tabs.forEach(b=>b.classList.toggle('is-active', b===btn));
        panels.forEach(p=>{ p.hidden = p.getAttribute('data-ui-panel')!==slug; });
      });
    });
  }
  document.addEventListener('DOMContentLoaded', function(){ bindTabs(); bindPreview(); });
})();

(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const form=document.querySelector('.acdc-ui-system-form.is-design-locked');
    if(!form) return;
    form.querySelectorAll('.acdc-ui-section:not([data-ui-panel="transfer"]) input, .acdc-ui-section:not([data-ui-panel="transfer"]) select, .acdc-ui-section:not([data-ui-panel="transfer"]) textarea').forEach(function(el){
      el.setAttribute('aria-disabled','true');
      el.addEventListener('keydown', function(e){ e.preventDefault(); });
    });
  });
})();

(function(){
  const settingMap={
    page_title_size:['page_title_size','page_title_weight'],
    h1_font_size:['h1_font_size','h1_font_weight'],
    h2_font_size:['h2_font_size','h2_font_weight'],
    h3_font_size:['h3_font_size','h3_font_weight'],
    h4_font_size:['h4_font_size','h4_font_weight'],
    section_title_size:['section_title_size','section_title_weight'],
    subtitle_size:['subtitle_size','subtitle_weight'],
    body_font_size:['body_font_size','body_font_weight'],
    small_text_size:['small_text_size','small_text_weight'],
    label_font_size:['label_font_size','label_font_weight'],
    input_font_size:['input_font_size','input_font_weight'],
    button_font_size:['button_font_size','button_font_weight'],
    table_header_font_size:['table_header_font_size','table_header_font_weight'],
    table_row_font_size:['table_row_font_size','table_row_font_weight'],
    badge_font_size:['badge_font_size','badge_font_weight'],
    menu_font_size:['menu_font_size','menu_font_weight'],
    help_text_size:['help_text_size'],
    error_text_size:['error_text_size'],
    kpi_value_size:['kpi_value_size','kpi_value_weight']
  };
  const aliases={page_title_weight:'page_title_size',h1_font_weight:'h1_font_size',h2_font_weight:'h2_font_size',h3_font_weight:'h3_font_size',h4_font_weight:'h4_font_size',section_title_weight:'section_title_size',subtitle_weight:'subtitle_size',body_font_weight:'body_font_size',small_text_weight:'small_text_size',label_font_weight:'label_font_size',input_font_weight:'input_font_size',button_font_weight:'button_font_size',table_header_font_weight:'table_header_font_size',table_row_font_weight:'table_row_font_size',badge_font_weight:'badge_font_size',menu_font_weight:'menu_font_size',kpi_value_weight:'kpi_value_size'};
  function clearHighlights(){document.querySelectorAll('.acdc-inspectable.acdc-highlight').forEach(el=>el.classList.remove('acdc-highlight'));document.querySelectorAll('.acdc-setting-focus').forEach(el=>el.classList.remove('acdc-setting-focus'));}
  function findInputByKey(key){return document.querySelector('.acdc-ui-admin-page [name="branding['+key+']"]');}
  function openTypographyPanel(){const tab=document.querySelector('.acdc-ui-subtab[data-ui-tab="type"]');if(tab && !tab.classList.contains('is-active')){tab.click();}}
  function focusSetting(key){const keys=settingMap[key]||[key];openTypographyPanel();let first=null;keys.forEach(function(k){const input=findInputByKey(k);if(input){const wrapper=input.closest('label')||input;wrapper.classList.add('acdc-setting-focus');if(!first)first=input;}});if(first){first.focus({preventScroll:true});const label=first.closest('label');if(label){label.scrollIntoView({behavior:'smooth',block:'center'});}}}
  function highlightBySetting(key){document.querySelectorAll('.acdc-inspectable.acdc-highlight').forEach(el=>el.classList.remove('acdc-highlight'));document.querySelectorAll('.acdc-inspectable[data-setting="'+key+'"]').forEach(el=>{el.classList.add('acdc-highlight');const panel=el.closest('[data-preview-panel]');if(panel){const slug=panel.getAttribute('data-preview-panel');const btn=document.querySelector('.acdc-ui-preview-tab[data-preview-tab="'+slug+'"]');if(btn&&!btn.classList.contains('is-active')){btn.click();}}setTimeout(()=>{try{el.scrollIntoView({behavior:'smooth',block:'center'});}catch(e){}},80);});}
  function resolveSettingFromInput(input){const name=input.getAttribute('name')||'';const m=name.match(/^branding\[([^\]]+)\]$/);if(!m)return '';const key=m[1];if(document.querySelector('.acdc-inspectable[data-setting="'+key+'"]'))return key;return aliases[key]||key;}
  function bindPreviewTabs(){document.querySelectorAll('.acdc-ui-preview-tab').forEach(function(btn){btn.addEventListener('click',function(){const slug=btn.getAttribute('data-preview-tab');document.querySelectorAll('.acdc-ui-preview-tab').forEach(b=>b.classList.toggle('is-active',b===btn));document.querySelectorAll('.acdc-ui-preview-panel').forEach(panel=>panel.classList.toggle('is-active',panel.getAttribute('data-preview-panel')===slug));});});}
  function bindInspector(){document.querySelectorAll('.acdc-inspectable[data-setting]').forEach(function(el){el.addEventListener('click',function(event){event.preventDefault();event.stopPropagation();clearHighlights();el.classList.add('acdc-highlight');focusSetting(el.getAttribute('data-setting'));const help=document.getElementById('acdc-ui-inspector-help');if(help){help.innerHTML='<strong>Élément sélectionné :</strong> '+(el.getAttribute('data-setting-label')||'réglage associé sélectionné.');}});});document.querySelectorAll('.acdc-ui-admin-page [name^="branding["]').forEach(function(input){['focus','input','change'].forEach(function(evt){input.addEventListener(evt,function(){const setting=resolveSettingFromInput(input);if(setting){document.querySelectorAll('.acdc-setting-focus').forEach(el=>el.classList.remove('acdc-setting-focus'));const wrap=input.closest('label')||input;wrap.classList.add('acdc-setting-focus');highlightBySetting(setting);}});});});}
  document.addEventListener('DOMContentLoaded',function(){if(!document.getElementById('acdc-ui-preview'))return;bindPreviewTabs();bindInspector();});
})();
