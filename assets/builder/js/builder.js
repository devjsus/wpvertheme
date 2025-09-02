/**
 * Template Builder - Versión Vanilla JavaScript
 * Esta versión no usa Lit debido a los problemas de carga
 */

(function ($) {
  'use strict';

  // Datos de la aplicación
  let templates = [];
  let selectedTemplate = null;
  let availableSections = [];
  let sectionSchemas = {};
  let loading = true;
  let currentMessage = null;
  let expandedSections = {};
  let activeTabs = {};

  // URL base de la API REST
  const apiBase = sectionsBuilderData.restUrl;
  const nonce = sectionsBuilderData.nonce;

  // Elementos DOM
  let $app;
  let $templatesList;
  let $sectionsList;
  let $editor;
  let $message;
  let $loading;
  let $saveButton;
  let $templateTitle;
  let $builder;

  // Inicializar la aplicación
  function init() {
      // Crear la estructura básica
      createAppStructure();

      // Cargar datos
      loadTemplates();
      loadAvailableSections();
      loadSectionSchemas();

      // Registrar eventos
      registerEvents();
  }

  // Crear la estructura básica de la aplicación
  function createAppStructure() {
      $app = $('#sections-builder-app');

      // Estructura HTML
      const structure = `
          <div id="sb-message" class="sb-message" style="display: none;"></div>
          
          <div id="sb-loading" class="sb-loading">
              <p>Cargando...</p>
          </div>
          
          <div id="sb-builder" class="sb-builder" style="display: none;">
              <div class="sb-sidebar">
                  <div class="sb-panel">
                      <h3 class="sb-panel-title">Plantillas Disponibles</h3>
                      <div class="sb-panel-content">
                          <ul id="sb-templates-list" class="sb-list"></ul>
                          <div style="margin-top: 15px">
                              <button id="sb-new-template" class="sb-button sb-button-primary">
                                  Nueva Plantilla
                              </button>
                          </div>
                      </div>
                  </div>
                  
                  <div class="sb-panel">
                      <h3 class="sb-panel-title">Secciones Disponibles</h3>
                      <div class="sb-panel-content">
                          <ul id="sb-sections-list" class="sb-list"></ul>
                      </div>
                  </div>
              </div>
              
              <div class="sb-content">
                  <div class="sb-header">
                      <h2 id="sb-template-title">Template Builder</h2>
                      <div>
                          <button id="sb-save-template" class="sb-button sb-button-primary" style="display: none;">
                              Guardar Plantilla
                          </button>
                      </div>
                  </div>
                  
                  <div id="sb-editor" class="sb-editor"></div>
              </div>
          </div>
      `;

      $app.html(structure);

      // Guardar referencias a elementos
      $templatesList = $('#sb-templates-list');
      $sectionsList = $('#sb-sections-list');
      $editor = $('#sb-editor');
      $message = $('#sb-message');
      $loading = $('#sb-loading');
      $saveButton = $('#sb-save-template');
      $templateTitle = $('#sb-template-title');
      $builder = $('#sb-builder');
  }

  // Registrar eventos
  function registerEvents() {
      // Crear nueva plantilla
      $('#sb-new-template').on('click', createNewTemplate);

      // Guardar plantilla
      $('#sb-save-template').on('click', saveTemplate);

      // Delegación de eventos para la lista de plantillas
      $templatesList.on('click', 'li.sb-list-item', function () {
          const templateId = $(this).data('id');
          console.log('Plantilla seleccionada:', templateId); // Debug
          loadTemplate(templateId);
      });

      // Delegación de eventos para la lista de secciones
      $sectionsList.on('click', 'li.sb-list-item', function () {
          const sectionId = $(this).data('id');
          addSection(sectionId);
      });

      // Delegación de eventos para las secciones
      $editor.on('click', '.sb-section-header', function () {
          const sectionId = $(this).closest('.sb-section').data('id');
          toggleSection(sectionId);
      });

      // Eliminar sección
      $editor.on('click', '.sb-remove-section', function (e) {
          e.stopPropagation();
          const sectionId = $(this).closest('.sb-section').data('id');
          removeSection(sectionId);
      });

      // Mover sección arriba
      $editor.on('click', '.sb-move-up', function (e) {
          e.stopPropagation();
          const sectionId = $(this).closest('.sb-section').data('id');
          moveSectionUp(sectionId);
      });

      // Mover sección abajo
      $editor.on('click', '.sb-move-down', function (e) {
          e.stopPropagation();
          const sectionId = $(this).closest('.sb-section').data('id');
          moveSectionDown(sectionId);
      });

      // Actualizar configuración de sección
      $editor.on('change', '.sb-setting-input', function () {
          const $this = $(this);
          const sectionId = $this.closest('.sb-section').data('id');
          const setting = $this.data('setting');
          let value = $this.val();

          // Convertir según el tipo de dato
          const type = $this.data('type');
          if (type === 'number') {
              value = parseFloat(value);
          } else if (type === 'boolean') {
              value = $this.is(':checked');
          } else if (type === 'json') {
              try {
                  value = JSON.parse(value);
              } catch (error) {
                  showMessage('error', 'JSON inválido: ' + error.message);
                  return;
              }
          }

          updateSectionSetting(sectionId, setting, value);
      });

      // Actualizar datos de plantilla
      $editor.on('change', '.sb-template-input', function () {
          const $this = $(this);
          const field = $this.data('field');
          const value = $this.val();

          updateTemplateField(field, value);
      });

      // Botón para seleccionar imagen
      $editor.on('click', '.sb-select-image', function (e) {
          e.preventDefault();

          const button = $(this);
          const inputId = button.data('input');
          const previewId = button.data('preview');

          // Si no existe frame, crearlo
          if (!window.wp || !window.wp.media) {
              console.error('WP Media no está disponible');
              return;
          }

          // Crear frame de medios
          const frame = wp.media({
              title: 'Seleccionar o subir una imagen',
              button: {
                  text: 'Usar esta imagen'
              },
              multiple: false
          });

          // Cuando se selecciona una imagen
          frame.on('select', function () {
              const attachment = frame.state().get('selection').first().toJSON();

              // Actualizar valor del input con la URL
              const $input = $('#' + inputId);
              $input.val(attachment.url).trigger('change');

              // Actualizar vista previa
              const $preview = $('#' + previewId);
              $preview.find('img').remove();
              $preview.prepend('<img src="' + attachment.url + '" alt="Vista previa" />');
              $preview.show();
          });

          // Abrir selector de medios
          frame.open();
      });

      // Botón para eliminar imagen
      $editor.on('click', '.sb-remove-image', function (e) {
          e.preventDefault();

          const button = $(this);
          const inputId = button.data('input');
          const previewId = button.data('preview');

          // Limpiar valor del input
          $('#' + inputId).val('').trigger('change');

          // Ocultar vista previa
          $('#' + previewId).hide().find('img').remove();
      });

      // Pestañas de sección (Configuración/Bloques)
      $editor.on('click', '.sb-tab-btn', function () {
          const $this = $(this);
          const sectionId = $this.data('section');
          const tab = $this.data('tab');

          console.log('Cambiando a pestaña:', tab, 'para sección:', sectionId);

          // Guardar la pestaña activa
          activeTabs[sectionId] = tab;

          // Cambiar pestaña activa
          $this.closest('.sb-section-tabs').find('.sb-tab-btn').removeClass('active');
          $this.addClass('active');

          // Mostrar contenido de pestaña seleccionada
          const $sectionContent = $this.closest('.sb-section-content');
          $sectionContent.find('.sb-tab-content').hide();
          $sectionContent.find(`#tab-${tab}-${sectionId}`).show();
      });

      // Añadir bloque
      $editor.on('click', '.sb-add-block', function () {
          const sectionId = $(this).data('section');
          const blockType = $(`#block-type-${sectionId}`).val();

          console.log('Click en añadir bloque:', { sectionId, blockType });

          if (blockType) {
              addBlock(sectionId, blockType);
          } else {
              console.error('No se seleccionó tipo de bloque');
          }
      });

      // Eliminar bloque
      $editor.on('click', '.sb-remove-block', function (e) {
          e.stopPropagation();

          const sectionId = $(this).data('section');
          const blockId = $(this).data('block');

          console.log('Click en eliminar bloque:', { sectionId, blockId });

          if (confirm('¿Estás seguro de que deseas eliminar este bloque?')) {
              removeBlock(sectionId, blockId);
          }
      });

      // Alternar visibilidad de bloque
      $editor.on('click', '.sb-toggle-block', function (e) {
          e.stopPropagation();

          const blockId = $(this).data('block');
          const $content = $(`#block-content-${blockId}`);

          console.log('Alternando visibilidad de bloque:', blockId);

          $content.toggle();
      });

      // Actualizar configuración de bloque
      $editor.on('change', '.sb-block-setting-input', function () {
          const $this = $(this);
          const blockId = $this.data('block');
          const setting = $this.data('setting');
          const sectionId = $this.data('section') || $this.closest('.sb-block').data('section') || $this.closest('.sb-section').data('id');
          let value = $this.val();

          console.log('Actualizar configuración de bloque:', {
              blockId,
              setting,
              value,
              sectionId
          });

          // Convertir según el tipo de dato
          const type = $this.data('type');
          if (type === 'number') {
              value = parseFloat(value);
          } else if (type === 'boolean') {
              value = $this.is(':checked');
          } else if (type === 'json') {
              try {
                  value = JSON.parse(value);
              } catch (error) {
                  showMessage('error', 'JSON inválido: ' + error.message);
                  return;
              }
          }

          // Usar el ID de sección si está disponible
          updateBlockSetting(blockId, setting, value, sectionId);
      });
  }

  // Cargar plantillas
  async function loadTemplates() {
      setLoading(true);

      try {
          // Usar AJAX en lugar de REST API
          const response = await jQuery.ajax({
              url: sectionsBuilderData.ajaxUrl,
              method: 'POST',
              data: {
                  action: 'get_templates',
                  nonce: sectionsBuilderData.nonce
              }
          });

          if (response.success) {
              templates = response.data;
              console.log('Plantillas cargadas:', templates); // Debug
              renderTemplatesList();
              setLoading(false);
          } else {
              throw new Error('Error al cargar las plantillas');
          }
      } catch (error) {
          console.error('Error:', error);
          showMessage('error', error.message);
          setLoading(false);
      }
  }

  // Cargar secciones disponibles
  async function loadAvailableSections() {
      try {
          const response = await jQuery.ajax({
              url: sectionsBuilderData.ajaxUrl,
              method: 'POST',
              data: {
                  action: 'get_sections',
                  nonce: sectionsBuilderData.nonce
              }
          });

          if (response.success) {
              availableSections = response.data;
              console.log('Secciones disponibles:', availableSections); // Debug
              renderSectionsList();
          } else {
              throw new Error('Error al cargar las secciones disponibles');
          }
      } catch (error) {
          console.error('Error:', error);
          showMessage('error', error.message);
      }
  }

  // Cargar esquemas de secciones
  async function loadSectionSchemas() {
      try {
          const response = await jQuery.ajax({
              url: sectionsBuilderData.ajaxUrl,
              method: 'POST',
              data: {
                  action: 'get_section_schemas',
                  nonce: sectionsBuilderData.nonce
              }
          });

          if (response.success) {
              sectionSchemas = response.data;
              console.log('Esquemas de secciones:', sectionSchemas); // Debug
          } else {
              throw new Error('Error al cargar los esquemas de secciones');
          }
      } catch (error) {
          console.error('Error:', error);
          showMessage('error', error.message);
      }
  }

  async function loadTemplate(templateName) {
      setLoading(true);

      try {
          console.log('Cargando plantilla específica:', templateName);

          const response = await jQuery.ajax({
              url: sectionsBuilderData.ajaxUrl,
              method: 'POST',
              data: {
                  action: 'get_template',
                  nonce: sectionsBuilderData.nonce,
                  template_name: templateName
              }
          });

          console.log('Respuesta recibida:', response);

          if (response.success) {
              // Guardar el template y corregir su estructura
              selectedTemplate = fixTemplateStructure(response.data);

              // Guardar el ID original de la plantilla para usarlo al guardar
              selectedTemplate._originalId = templateName;

              console.log('Plantilla cargada y estructura corregida:', selectedTemplate);

              // Actualizar UI
              updateActiveTemplate();
              initExpandedSections();
              renderTemplateEditor();
              setLoading(false);
          } else {
              throw new Error(response.data?.message || 'Error al cargar la plantilla');
          }
      } catch (error) {
          console.error('Error al cargar plantilla:', error);
          showMessage('error', error.message);
          setLoading(false);
      }
  }

  async function saveTemplate() {
      if (!selectedTemplate) {
          showMessage('error', 'No hay una plantilla seleccionada');
          return;
      }

      setLoading(true);

      try {
          // Usar el ID original si está disponible
          const templateName = selectedTemplate._originalId || selectedTemplate.name || 'template';
          console.log('Guardando plantilla con ID:', templateName);

          // Crear una copia profunda de la plantilla para enviar
          const templateCopy = JSON.parse(JSON.stringify(selectedTemplate));

          // Debug: Verificar estructura de los bloques antes de enviar
          if (templateCopy.sections) {
              Object.keys(templateCopy.sections).forEach(sectionId => {
                  const section = templateCopy.sections[sectionId];
                  console.log(`Sección ${sectionId}, tipo: ${section.section_id}`);

                  // Verificar si blocks existe y tiene elementos
                  if (section.blocks) {
                      console.log(`  Bloques encontrados:`, section.blocks);

                      // Asegurarse de que los bloques estén en el formato correcto (objeto)
                      if (Array.isArray(section.blocks) && section.blocks.length === 0) {
                          // Si es un array vacío, convertirlo a un objeto vacío
                          section.blocks = {};
                          console.log('  Convertido array vacío de bloques a objeto');
                      }
                  } else {
                      console.log('  No hay bloques en esta sección');
                      // Inicializar como objeto si no existe
                      section.blocks = {};
                  }
              });
          }

          const formData = new FormData();
          formData.append('action', 'save_template');
          formData.append('nonce', sectionsBuilderData.nonce);
          formData.append('template_name', templateName);

          // Convertir a JSON correctamente
          const templateJSON = JSON.stringify(templateCopy);
          console.log('Datos a enviar:', templateJSON);
          formData.append('template_data', templateJSON);

          const response = await jQuery.ajax({
              url: sectionsBuilderData.ajaxUrl,
              method: 'POST',
              processData: false,
              contentType: false,
              data: formData
          });

          console.log('Respuesta de guardado:', response);

          if (response.success) {
              showMessage('success', response.data?.message || 'Plantilla guardada correctamente');

              // Mantener el ID original después de guardar
              selectedTemplate._originalId = templateName;

              // Recargar la lista de plantillas
              await loadTemplates();

              setLoading(false);
          } else {
              throw new Error(response.data?.message || 'Error al guardar la plantilla');
          }
      } catch (error) {
          console.error('Error al guardar:', error);
          showMessage('error', error.message);
          setLoading(false);
      }
  }

  // Crear una nueva plantilla
  function createNewTemplate() {
      // Crear plantilla vacía con estructura correcta
      selectedTemplate = {
          name: 'Nueva Plantilla',
          description: 'Descripción de la plantilla',
          template: true,
          sections: {},  // Objeto vacío para secciones
          order: []      // Array vacío para el orden
      };

      console.log('Nueva plantilla creada con estructura:', selectedTemplate);

      // Actualizar UI
      updateActiveTemplate();
      initExpandedSections();
      renderTemplateEditor();
  }

  // Añadir sección a la plantilla
  function addSection(sectionId) {
      if (!selectedTemplate) {
          showMessage('error', 'No hay una plantilla seleccionada');
          return;
      }

      // Asegurarse de que existe la propiedad sections
      if (!selectedTemplate.sections) {
          selectedTemplate.sections = {};
      }

      // Generar un ID único para la sección
      const uniqueId = `section_${Date.now()}`;

      // Crear la nueva sección
      selectedTemplate.sections[uniqueId] = {
          section_id: sectionId,
          settings: {},
          blocks: {}  // Inicializar como objeto vacío en lugar de array
      };

      // Actualizar el orden
      if (!selectedTemplate.order) {
          selectedTemplate.order = [];
      }

      selectedTemplate.order.push(uniqueId);

      // Expandir la nueva sección
      expandedSections[uniqueId] = true;

      // Actualizar UI
      renderTemplateEditor();
  }

  // Eliminar sección
  function removeSection(sectionId) {
      if (!selectedTemplate || !selectedTemplate.sections) {
          return;
      }

      // Eliminar la sección
      delete selectedTemplate.sections[sectionId];

      // Actualizar el orden
      if (selectedTemplate.order) {
          selectedTemplate.order = selectedTemplate.order.filter(id => id !== sectionId);
      }

      // Actualizar UI
      renderTemplateEditor();
  }

  // Mover sección arriba
  function moveSectionUp(sectionId) {
      if (!selectedTemplate || !selectedTemplate.order) {
          return;
      }

      const currentIndex = selectedTemplate.order.indexOf(sectionId);
      if (currentIndex <= 0) {
          return; // Ya está en la primera posición
      }

      // Intercambiar posiciones
      const newOrder = [...selectedTemplate.order];
      const temp = newOrder[currentIndex];
      newOrder[currentIndex] = newOrder[currentIndex - 1];
      newOrder[currentIndex - 1] = temp;

      selectedTemplate.order = newOrder;

      // Actualizar UI
      renderTemplateEditor();
  }

  // Mover sección abajo
  function moveSectionDown(sectionId) {
      if (!selectedTemplate || !selectedTemplate.order) {
          return;
      }

      const currentIndex = selectedTemplate.order.indexOf(sectionId);
      if (currentIndex === -1 || currentIndex >= selectedTemplate.order.length - 1) {
          return; // No existe o ya está en la última posición
      }

      // Intercambiar posiciones
      const newOrder = [...selectedTemplate.order];
      const temp = newOrder[currentIndex];
      newOrder[currentIndex] = newOrder[currentIndex + 1];
      newOrder[currentIndex + 1] = temp;

      selectedTemplate.order = newOrder;

      // Actualizar UI
      renderTemplateEditor();
  }

  // Alternar sección expandida/colapsada
  function toggleSection(sectionId) {
      expandedSections[sectionId] = !expandedSections[sectionId];
      renderTemplateEditor();
  }

  // Actualizar configuración de sección
  function updateSectionSetting(sectionId, key, value) {
      if (!selectedTemplate || !selectedTemplate.sections || !selectedTemplate.sections[sectionId]) {
          return;
      }

      // Asegurarse de que existe settings
      if (!selectedTemplate.sections[sectionId].settings) {
          selectedTemplate.sections[sectionId].settings = {};
      }

      // Actualizar el valor
      selectedTemplate.sections[sectionId].settings[key] = value;
  }

  // Actualizar campo de plantilla
  function updateTemplateField(field, value) {
      if (!selectedTemplate) {
          return;
      }

      // Guardar el valor anterior para el nombre
      if (field === 'name' && selectedTemplate.name !== value) {
          // Conservamos el ID original aunque el nombre cambie
          if (!selectedTemplate._originalId) {
              selectedTemplate._originalId = selectedTemplate.name;
          }
      }

      selectedTemplate[field] = value;

      // Actualizar título si es el campo name
      if (field === 'name') {
          $templateTitle.text(value || 'Sin título');
      }
  }

  // Inicializar secciones expandidas
  function initExpandedSections() {
      expandedSections = {};

      if (!selectedTemplate || !selectedTemplate.sections) {
          return;
      }

      // Determinar el orden de las secciones
      let sectionsOrder = [];

      if (selectedTemplate.order && Array.isArray(selectedTemplate.order)) {
          sectionsOrder = selectedTemplate.order;
      } else {
          sectionsOrder = Object.keys(selectedTemplate.sections);
      }

      // Inicializar como colapsadas
      sectionsOrder.forEach(sectionId => {
          expandedSections[sectionId] = false;
      });

      // Expandir la primera sección si existe
      if (sectionsOrder.length > 0) {
          expandedSections[sectionsOrder[0]] = true;
      }
  }

  // Actualizar plantilla activa en la UI
  function updateActiveTemplate() {
      // Actualizar título
      $templateTitle.text(selectedTemplate ? selectedTemplate.name || 'Sin título' : 'Template Builder');

      // Mostrar botón de guardar si hay plantilla seleccionada
      if (selectedTemplate) {
          $saveButton.show();
      } else {
          $saveButton.hide();
      }

      // Actualizar elemento activo en la lista
      $templatesList.find('li').removeClass('active');

      if (selectedTemplate && selectedTemplate._originalId) {
          $templatesList.find(`li[data-id="${selectedTemplate._originalId}"]`).addClass('active');
      }
  }

  // Renderizar lista de plantillas
  function renderTemplatesList() {
      $templatesList.empty();

      if (!templates || Object.keys(templates).length === 0) {
          $templatesList.html('<div class="sb-empty">No hay plantillas disponibles</div>');
          return;
      }

      // Crear elementos de lista
      Object.entries(templates).forEach(([id, template]) => {
          const name = template.name || id;
          console.log('Renderizando plantilla:', id, name); // Debug

          const $item = $(`<li class="sb-list-item" data-id="${id}">${name}</li>`);

          if (selectedTemplate && (id === selectedTemplate._originalId)) {
              $item.addClass('active');
          }

          $templatesList.append($item);
      });
  }

  // Renderizar lista de secciones disponibles
  function renderSectionsList() {
      $sectionsList.empty();

      if (!availableSections || Object.keys(availableSections).length === 0) {
          $sectionsList.html('<div class="sb-empty">No hay secciones disponibles</div>');
          return;
      }

      // Crear elementos de lista
      Object.entries(availableSections).forEach(([id, section]) => {
          const name = section.name || id;
          const $item = $(`
              <li class="sb-list-item" data-id="${id}">
                  ${name}
                  <small style="display: block; color: #666;">Hacer clic para añadir</small>
              </li>
          `);

          $sectionsList.append($item);
      });
  }

  // Renderizar editor de plantilla
  function renderTemplateEditor() {
      $editor.empty();

      if (!selectedTemplate) {
          $editor.html('<div class="sb-empty">Selecciona una plantilla para editar o crea una nueva</div>');
          return;
      }

      // Panel de propiedades de la plantilla
      const propertiesPanel = `
          <div class="sb-panel">
              <h3 class="sb-panel-title">Propiedades de la Plantilla</h3>
              <div class="sb-panel-content">
                  <div class="sb-form-group">
                      <label class="sb-form-label">Nombre</label>
                      <input class="sb-form-input sb-template-input" type="text" 
                             value="${selectedTemplate.name || ''}"
                             data-field="name">
                  </div>
                  
                  <div class="sb-form-group">
                      <label class="sb-form-label">Descripción</label>
                      <textarea class="sb-form-textarea sb-template-input"
                                data-field="description">${selectedTemplate.description || ''}</textarea>
                  </div>
              </div>
          </div>
      `;

      // Panel de secciones
      const sectionsPanel = `
          <div class="sb-panel">
              <h3 class="sb-panel-title">Secciones</h3>
              <div class="sb-panel-content">
                  ${renderSections()}
              </div>
          </div>
      `;

      $editor.html(propertiesPanel + sectionsPanel);
  }

  // Renderizar secciones
  function renderSections() {
      if (!selectedTemplate || !selectedTemplate.sections) {
          return '<div class="sb-empty">No hay secciones en esta plantilla</div>';
      }

      const sectionsOrder = selectedTemplate.order || Object.keys(selectedTemplate.sections);

      if (sectionsOrder.length === 0) {
          return '<div class="sb-empty">No hay secciones en esta plantilla</div>';
      }

      let html = '';

      sectionsOrder.forEach(sectionId => {
          const section = selectedTemplate.sections[sectionId];
          if (!section) return;

          const isExpanded = expandedSections[sectionId] || false;
          const sectionType = section.section_id;
          const sectionInfo = availableSections[sectionType] || { name: sectionType };

          html += `
              <div class="sb-section" data-id="${sectionId}">
                  <div class="sb-section-header">
                      <h3 class="sb-section-title">${sectionInfo.name || 'Sección'}</h3>
                      <div class="sb-section-actions">
                          <button class="sb-button sb-button-secondary sb-button-sm sb-move-up">
                              ↑
                          </button>
                          <button class="sb-button sb-button-secondary sb-button-sm sb-move-down">
                              ↓
                          </button>
                          <button class="sb-button sb-button-danger sb-button-sm sb-remove-section">
                              ×
                          </button>
                      </div>
                  </div>
                  
                  ${isExpanded ? renderSectionSettings(sectionId, section) : ''}
              </div>
          `;
      });

      return html;
  }

  // Renderizar configuración de sección (con soporte para bloques)
  // Renderizar configuración de sección (con soporte para bloques)
  function renderSectionSettings(sectionId, section) {
      const sectionType = section.section_id;
      const settings = section.settings || {};
      const blocks = section.blocks || {};

      // Verificar si hay esquemas disponibles
      if (!sectionSchemas || !sectionSchemas[sectionType]) {
          console.warn(`No se encontró esquema para la sección tipo: ${sectionType}`);
          return `
          <div class="sb-section-content">
              <div class="sb-form-group">
                  <p class="sb-form-help">No hay configuración disponible para esta sección.</p>
              </div>
          </div>
      `;
      }

      const schema = sectionSchemas[sectionType]?.schema?.properties || {};
      const blocksSchema = sectionSchemas[sectionType]?.schema?.blocks || {};

      // Determinar qué pestaña está activa (por defecto 'settings')
      const activeTab = activeTabs[sectionId] || 'settings';

      let html = `
      <div class="sb-section-content">
          <div class="sb-section-tabs">
              <button class="sb-tab-btn ${activeTab === 'settings' ? 'active' : ''}" data-tab="settings" data-section="${sectionId}">Configuración</button>
              <button class="sb-tab-btn ${activeTab === 'blocks' ? 'active' : ''}" data-tab="blocks" data-section="${sectionId}">Bloques</button>
          </div>
          
          <div class="sb-tab-content" id="tab-settings-${sectionId}" style="${activeTab === 'settings' ? '' : 'display: none;'}">
              <div class="sb-form-group">
                  <label class="sb-form-label">ID de la Sección</label>
                  <input class="sb-form-input" type="text" value="${sectionType}" disabled />
                  <p class="sb-form-help">Tipo de sección (no editable)</p>
              </div>
  `;

      // Renderizar campos según el esquema
      Object.entries(schema).forEach(([key, property]) => {
          // Prevenir errores si property es undefined o null
          if (!property) return;

          // Determinar el tipo de campo
          const type = property.type || 'string';
          const value = settings[key] !== undefined ? settings[key] : (property.default || '');

          // Para campos de tipo imagen, usar el selector de medios de WordPress
          if (type === 'string' && (key === 'image' || property.format === 'image')) {
              const imageUrl = value || '';
              const hasImage = imageUrl !== '';
              const previewStyle = hasImage ? '' : 'display: none;';
              const imageId = `image-field-${sectionId}-${key}`;
              const previewId = `image-preview-${sectionId}-${key}`;

              html += `
              <div class="sb-form-group">
                  <label class="sb-form-label">${property.title || key}</label>
                  <div class="sb-image-field">
                      <input type="text" 
                          id="${imageId}"
                          class="sb-form-input sb-setting-input" 
                          value="${imageUrl}"
                          data-setting="${key}"
                          data-type="string"
                      />
                      <button class="sb-button sb-button-secondary sb-select-image" 
                              data-input="${imageId}" 
                              data-preview="${previewId}">
                          Seleccionar Imagen
                      </button>
                  </div>
                  <div id="${previewId}" class="sb-image-preview" style="${previewStyle}">
                      ${hasImage ? `<img src="${imageUrl}" alt="Vista previa" />` : ''}
                      <button class="sb-button sb-button-danger sb-remove-image" 
                              data-input="${imageId}" 
                              data-preview="${previewId}">
                          ×
                      </button>
                  </div>
                  ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
              </div>
          `;
          }
          else if (type === 'string') {
              html += `
              <div class="sb-form-group">
                  <label class="sb-form-label">${property.title || key}</label>
                  <input class="sb-form-input sb-setting-input" type="text" 
                    value="${value}"
                    data-setting="${key}"
                    data-type="string"
                  />
                  ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
              </div>
          `;
          } else if (type === 'number') {
              html += `
              <div class="sb-form-group">
                  <label class="sb-form-label">${property.title || key}</label>
                  <input class="sb-form-input sb-setting-input" type="number" 
                    value="${value}"
                    min="${property.minimum || ''}"
                    max="${property.maximum || ''}"
                    step="${property.step || 1}"
                    data-setting="${key}"
                    data-type="number"
                  />
                  ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
              </div>
          `;
          } else if (type === 'boolean') {
              html += `
              <div class="sb-form-group">
                  <label class="sb-form-label">
                      <input type="checkbox" 
                          class="sb-setting-input"
                          ${value ? 'checked' : ''}
                          data-setting="${key}"
                          data-type="boolean"
                      />
                      ${property.title || key}
                  </label>
                  ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
              </div>
          `;
          } else if (type === 'color') {
              html += `
              <div class="sb-form-group sb-form-color">
                  <div class="sb-color-preview" style="background-color: ${value}"></div>
                  <div style="flex: 1">
                      <label class="sb-form-label">${property.title || key}</label>
                      <input class="sb-form-input sb-setting-input" type="color" 
                          value="${value}"
                          data-setting="${key}"
                          data-type="string"
                      />
                      ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                  </div>
              </div>
          `;
          } else if (property.enum) {
              html += `
              <div class="sb-form-group">
                  <label class="sb-form-label">${property.title || key}</label>
                  <select class="sb-form-select sb-setting-input"
                      data-setting="${key}"
                      data-type="string"
                  >
                      ${property.enum.map(option => `
                          <option value="${option}" ${value === option ? 'selected' : ''}>${option}</option>
                      `).join('')}
                  </select>
                  ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
              </div>
          `;
          } else if (type === 'array' || type === 'object') {
              // Para arrays y objetos, mostramos un textarea con JSON
              let jsonValue;
              try {
                  jsonValue = JSON.stringify(value || (type === 'array' ? [] : {}), null, 2);
              } catch (e) {
                  jsonValue = type === 'array' ? '[]' : '{}';
              }

              html += `
              <div class="sb-form-group">
                  <label class="sb-form-label">${property.title || key}</label>
                  <textarea class="sb-form-textarea sb-setting-input" 
                      data-setting="${key}"
                      data-type="json"
                  >${jsonValue}</textarea>
                  ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
              </div>
          `;
          }
      });

      html += `
          </div>
          
          <div class="sb-tab-content" id="tab-blocks-${sectionId}" style="${activeTab === 'blocks' ? '' : 'display: none;'}">
              <div class="sb-blocks-container">
  `;

      // Si hay esquemas de bloques disponibles, mostrar el selector
      if (Object.keys(blocksSchema).length > 0) {
          html += `
          <div class="sb-block-selector">
              <select class="sb-form-select" id="block-type-${sectionId}">
                  ${Object.entries(blocksSchema).map(([blockType, blockSchema]) => `
                      <option value="${blockType}">${blockSchema.name || blockType}</option>
                  `).join('')}
              </select>
              <button class="sb-button sb-button-primary sb-add-block" data-section="${sectionId}">
                  Añadir Bloque
              </button>
          </div>
      `;
      } else {
          html += `
          <div class="sb-empty">Esta sección no tiene bloques configurables.</div>
      `;
      }

      // Asegurarnos de que blocks sea un objeto y no un array 
      if (blocks) {
          if (Array.isArray(blocks)) {
              console.warn(`Sección ${sectionId}: blocks es un array, debería ser un objeto.`);
              // Si es un array, convertir a objeto (esto es temporal, la estructura debería arreglarse antes)
              let tempBlocks = {};
              blocks.forEach((block, index) => {
                  if (block && block.id) {
                      tempBlocks[block.id] = block;
                  } else {
                      tempBlocks[`temp_block_${index}`] = block;
                  }
              });
              blocks = tempBlocks;
          }
      }

      // Renderizar bloques existentes
      if (blocks && Object.keys(blocks).length > 0) {
          html += `<div class="sb-blocks-list">`;

          Object.entries(blocks).forEach(([blockId, blockData]) => {
              if (!blockData) {
                  console.warn(`Bloque inválido encontrado en ${blockId}:`, blockData);
                  return; // Saltar bloques inválidos
              }

              const blockType = blockData.block_type;
              if (!blockType) {
                  console.warn(`Bloque ${blockId} no tiene block_type definido:`, blockData);
                  return; // Saltar bloques sin tipo
              }

              const blockSchema = blocksSchema[blockType] || null;
              const blockName = blockSchema ? (blockSchema.name || blockType) : (blockData.name || blockType);

              html += `
          <div class="sb-block" data-id="${blockId}" data-type="${blockType}" data-section="${sectionId}">
              <div class="sb-block-header">
                  <h4 class="sb-block-title">${blockName}</h4>
                  <div class="sb-block-actions">
                      <button class="sb-button sb-button-secondary sb-button-sm sb-toggle-block" 
                              data-block="${blockId}" 
                              data-section="${sectionId}">
                          ↕
                      </button>
                      <button class="sb-button sb-button-danger sb-button-sm sb-remove-block" 
                              data-section="${sectionId}" 
                              data-block="${blockId}">
                          ×
                      </button>
                  </div>
              </div>
              <div class="sb-block-content" id="block-content-${blockId}" style="display: none;">
          `;

              if (blockSchema && blockSchema.properties) {
                  // Asegurarnos de que settings exista
                  const settings = blockData.settings || {};

                  Object.entries(blockSchema.properties).forEach(([key, property]) => {
                      const value = settings[key] !== undefined ? settings[key] : (property.default || '');

                      // Renderizar campos según el tipo (similar a como se hizo para la configuración de la sección)
                      if (property.type === 'string' && (key === 'image' || property.format === 'image')) {
                          const imageUrl = value || '';
                          const hasImage = imageUrl !== '';
                          const previewStyle = hasImage ? '' : 'display: none;';
                          const imageId = `image-block-${blockId}-${key}`;
                          const previewId = `image-preview-block-${blockId}-${key}`;

                          html += `
                      <div class="sb-form-group">
                          <label class="sb-form-label">${property.title || key}</label>
                          <div class="sb-image-field">
                              <input type="text" 
                                  id="${imageId}"
                                  class="sb-form-input sb-block-setting-input" 
                                  value="${imageUrl}"
                                  data-block="${blockId}"
                                  data-section="${sectionId}"
                                  data-setting="${key}"
                                  data-type="string"
                              />
                              <button class="sb-button sb-button-secondary sb-select-image" 
                                      data-input="${imageId}" 
                                      data-preview="${previewId}">
                                  Seleccionar Imagen
                              </button>
                          </div>
                          <div id="${previewId}" class="sb-image-preview" style="${previewStyle}">
                              ${hasImage ? `<img src="${imageUrl}" alt="Vista previa" />` : ''}
                              <button class="sb-button sb-button-danger sb-remove-image" 
                                      data-input="${imageId}" 
                                      data-preview="${previewId}">
                                  ×
                              </button>
                          </div>
                          ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                      </div>
                  `;
                      }
                      else if (property.type === 'string') {
                          html += `
                      <div class="sb-form-group">
                          <label class="sb-form-label">${property.title || key}</label>
                          <input class="sb-form-input sb-block-setting-input" type="text" 
                            value="${value}"
                            data-block="${blockId}"
                            data-section="${sectionId}"
                            data-setting="${key}"
                            data-type="string"
                          />
                          ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                      </div>
                  `;
                      } else if (property.type === 'number') {
                          html += `
                      <div class="sb-form-group">
                          <label class="sb-form-label">${property.title || key}</label>
                          <input class="sb-form-input sb-block-setting-input" type="number" 
                            value="${value}"
                            min="${property.minimum || ''}"
                            max="${property.maximum || ''}"
                            step="${property.step || 1}"
                            data-block="${blockId}"
                            data-section="${sectionId}"
                            data-setting="${key}"
                            data-type="number"
                          />
                          ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                      </div>
                  `;
                      } else if (property.type === 'boolean') {
                          html += `
                      <div class="sb-form-group">
                          <label class="sb-form-label">
                              <input type="checkbox" 
                                  class="sb-block-setting-input"
                                  ${value ? 'checked' : ''}
                                  data-block="${blockId}"
                                  data-section="${sectionId}"
                                  data-setting="${key}"
                                  data-type="boolean"
                              />
                              ${property.title || key}
                          </label>
                          ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                      </div>
                  `;
                      } else if (property.type === 'color') {
                          html += `
                      <div class="sb-form-group sb-form-color">
                          <div class="sb-color-preview" style="background-color: ${value}"></div>
                          <div style="flex: 1">
                              <label class="sb-form-label">${property.title || key}</label>
                              <input class="sb-form-input sb-block-setting-input" type="color" 
                                  value="${value}"
                                  data-block="${blockId}"
                                  data-section="${sectionId}"
                                  data-setting="${key}"
                                  data-type="string"
                              />
                              ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                          </div>
                      </div>
                  `;
                      } else if (property.enum) {
                          html += `
                      <div class="sb-form-group">
                          <label class="sb-form-label">${property.title || key}</label>
                          <select class="sb-form-select sb-block-setting-input"
                              data-block="${blockId}"
                              data-section="${sectionId}"
                              data-setting="${key}"
                              data-type="string"
                          >
                              ${property.enum.map(option => `
                                  <option value="${option}" ${value === option ? 'selected' : ''}>${option}</option>
                              `).join('')}
                          </select>
                          ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                      </div>
                  `;
                      } else if (property.type === 'array' || property.type === 'object') {
                          // Para arrays y objetos, mostramos un textarea con JSON
                          let jsonValue;
                          try {
                              jsonValue = JSON.stringify(value || (property.type === 'array' ? [] : {}), null, 2);
                          } catch (e) {
                              jsonValue = property.type === 'array' ? '[]' : '{}';
                          }

                          html += `
                      <div class="sb-form-group">
                          <label class="sb-form-label">${property.title || key}</label>
                          <textarea class="sb-form-textarea sb-block-setting-input" 
                              data-block="${blockId}"
                              data-section="${sectionId}"
                              data-setting="${key}"
                              data-type="json"
                          >${jsonValue}</textarea>
                          ${property.description ? `<p class="sb-form-help">${property.description}</p>` : ''}
                      </div>
                  `;
                      }
                  });
              } else {
                  html += `<div class="sb-empty">No hay esquema disponible para este tipo de bloque.</div>`;
              }

              html += `
              </div>
          </div>
          `;
          });

          html += `</div>`;
      } else {
          html += `<div class="sb-empty">No hay bloques añadidos a esta sección.</div>`;
      }

      html += `
              </div>
          </div>
      </div>
  `;

      return html;
  }

  // Añadir un nuevo bloque a una sección
  // Añadir un nuevo bloque a una sección
  function addBlock(sectionId, blockType) {
      console.log('Añadiendo bloque:', { sectionId, blockType });

      if (!selectedTemplate || !selectedTemplate.sections || !selectedTemplate.sections[sectionId]) {
          showMessage('error', 'No se pudo añadir el bloque. Sección no encontrada.');
          return;
      }

      // Obtener la sección
      const section = selectedTemplate.sections[sectionId];

      // Inicializar objeto de bloques si no existe o es un array vacío
      if (!section.blocks || Array.isArray(section.blocks)) {
          console.log('Inicializando section.blocks como objeto vacío');
          section.blocks = {};
      }

      // Generar ID único para el bloque
      const blockId = `block_${Date.now()}`;

      // Crear nuevo bloque con propiedades requeridas
      section.blocks[blockId] = {
          block_type: blockType,
          settings: {}
      };

      console.log(`Bloque creado con ID: ${blockId}, tipo: ${blockType}`);
      console.log('Estado actual de los bloques:', section.blocks);

      // Inicializar con valores por defecto si hay esquema disponible
      const blockSchema = sectionSchemas[section.section_id]?.schema?.blocks?.[blockType];
      if (blockSchema && blockSchema.properties) {
          Object.entries(blockSchema.properties).forEach(([key, property]) => {
              if (property.default !== undefined) {
                  section.blocks[blockId].settings[key] = property.default;
              }
          });
      }

      // Asegurarse de que estamos en la pestaña de bloques
      activeTabs[sectionId] = 'blocks';

      // Actualizar UI
      renderTemplateEditor();

      // Mostrar mensaje de éxito
      showMessage('success', 'Bloque añadido correctamente');

      // Expandir el bloque recién añadido
      setTimeout(() => {
          const $blockContent = $(`#block-content-${blockId}`);
          if ($blockContent.length) {
              $blockContent.show();
          }
      }, 100);
  }

  function removeBlock(sectionId, blockId) {
      console.log('Eliminando bloque:', { sectionId, blockId });

      if (!selectedTemplate || !selectedTemplate.sections || !selectedTemplate.sections[sectionId]) {
          showMessage('error', 'No se pudo eliminar el bloque. Sección no encontrada.');
          return;
      }

      const section = selectedTemplate.sections[sectionId];

      // Verificar si blocks es un objeto y existe el blockId
      if (typeof section.blocks === 'object' && section.blocks !== null && section.blocks[blockId]) {
          // Eliminar el bloque del objeto
          delete section.blocks[blockId];
          showMessage('success', 'Bloque eliminado correctamente');

          // Verificar si quedaron bloques
          const remainingBlocks = Object.keys(section.blocks).length;
          console.log(`Bloques restantes en la sección: ${remainingBlocks}`);

          // Actualizar UI
          renderTemplateEditor();
      }
      // Si blocks es un array, buscar y eliminar el bloque por id
      else if (Array.isArray(section.blocks)) {
          console.warn('La propiedad blocks es un array. Convirtiendo a objeto...');

          // Convertir el array a objeto para consistencia
          const newBlocks = {};
          let found = false;

          section.blocks.forEach((block, index) => {
              if (block && block.id === blockId) {
                  found = true;
                  // No incluir este bloque en el nuevo objeto
              } else {
                  // Usar el ID del bloque si existe, o crear uno nuevo
                  const id = block && block.id ? block.id : `block_${Date.now()}_${index}`;
                  newBlocks[id] = block;
              }
          });

          // Reemplazar el array con el objeto
          section.blocks = newBlocks;

          if (found) {
              showMessage('success', 'Bloque eliminado correctamente y estructura corregida');
          } else {
              showMessage('warning', 'Bloque no encontrado, pero se ha corregido la estructura');
          }

          // Actualizar UI
          renderTemplateEditor();
      } else {
          showMessage('error', 'No se pudo eliminar el bloque. Estructura de bloques inválida.');

          // Inicializar como objeto vacío si no existe o es inválido
          section.blocks = {};
          renderTemplateEditor();
      }

      // Debug: verificar estructura después de eliminar
      console.log('Estructura de bloques después de eliminar:', section.blocks);
  }

  // Actualizar configuración de un bloque
  function updateBlockSetting(blockId, key, value, sectionId = null) {
      // Si tenemos el ID de sección, actualiza directamente
      if (sectionId && selectedTemplate && selectedTemplate.sections &&
          selectedTemplate.sections[sectionId] &&
          selectedTemplate.sections[sectionId].blocks &&
          selectedTemplate.sections[sectionId].blocks[blockId]) {

          console.log(`Actualizando bloque ${blockId} en sección específica ${sectionId}, propiedad: ${key}`, value);

          // Asegurarse de que existe settings
          if (!selectedTemplate.sections[sectionId].blocks[blockId].settings) {
              selectedTemplate.sections[sectionId].blocks[blockId].settings = {};
          }

          // Actualizar el valor
          selectedTemplate.sections[sectionId].blocks[blockId].settings[key] = value;
          return true;
      }

      // De lo contrario, buscar en todas las secciones (fallback)
      let found = false;

      if (selectedTemplate && selectedTemplate.sections) {
          Object.entries(selectedTemplate.sections).forEach(([secId, section]) => {
              if (section.blocks && section.blocks[blockId]) {
                  console.log(`Actualizando bloque ${blockId} en sección encontrada ${secId}, propiedad: ${key}`, value);

                  // Asegurarse de que existe settings
                  if (!section.blocks[blockId].settings) {
                      section.blocks[blockId].settings = {};
                  }

                  // Actualizar el valor
                  section.blocks[blockId].settings[key] = value;
                  found = true;
              }
          });
      }

      if (!found) {
          console.warn(`Bloque con ID ${blockId} no encontrado.`);
          return false;
      }

      return true;
  }

  function debugBlocksStructure() {
      if (!selectedTemplate || !selectedTemplate.sections) {
          console.log('No hay plantilla o secciones para depurar');
          return;
      }

      console.log('=== DEPURACIÓN DE ESTRUCTURA DE BLOQUES ===');

      Object.entries(selectedTemplate.sections).forEach(([sectionId, section]) => {
          console.log(`Sección: ${sectionId}, Tipo: ${section.section_id}`);

          if (!section.blocks) {
              console.log('  No tiene propiedad blocks definida');
              return;
          }

          if (Array.isArray(section.blocks)) {
              console.log(`  blocks es un array con ${section.blocks.length} elementos`);
          } else if (typeof section.blocks === 'object') {
              const blockKeys = Object.keys(section.blocks);
              console.log(`  blocks es un objeto con ${blockKeys.length} llaves: ${blockKeys.join(', ')}`);

              blockKeys.forEach(blockId => {
                  const block = section.blocks[blockId];
                  console.log(`    Bloque: ${blockId}, Tipo: ${block.block_type}`);
                  console.log(`      Configuraciones: ${Object.keys(block.settings || {}).length}`);
              });
          } else {
              console.log(`  blocks es de tipo ${typeof section.blocks}`);
          }
      });

      console.log('=== FIN DE DEPURACIÓN ===');
  }

  function fixTemplateStructure(template) {
      if (!template) return template;

      console.log('Corrigiendo estructura del template...');

      // Asegurarse de que existe la propiedad sections
      if (!template.sections) {
          template.sections = {};
      }

      // Asegurarse de que existe la propiedad order
      if (!template.order) {
          template.order = Object.keys(template.sections);
      }

      // Recorrer todas las secciones
      Object.entries(template.sections).forEach(([sectionId, section]) => {
          // Asegurarse de que existe la propiedad settings
          if (!section.settings) {
              section.settings = {};
          }

          // Corregir la estructura de bloques
          if (section.blocks === undefined || section.blocks === null) {
              // Si no tiene bloques, inicializar como objeto vacío
              section.blocks = {};
          }
          else if (Array.isArray(section.blocks)) {
              // Si es un array, convertir a objeto
              console.log(`Sección ${sectionId}: Convirtiendo array de bloques a objeto`);

              const blocksObj = {};

              section.blocks.forEach((block, index) => {
                  if (block) {
                      // Usar el ID existente o crear uno nuevo
                      const blockId = block.id || `block_${Date.now()}_${index}`;
                      blocksObj[blockId] = block;

                      // Asegurarse de que tiene las propiedades necesarias
                      if (!block.block_type) {
                          block.block_type = 'unknown';
                      }

                      if (!block.settings) {
                          block.settings = {};
                      }
                  }
              });

              section.blocks = blocksObj;
          }
          else if (typeof section.blocks === 'object') {
              // Si ya es un objeto, verificar que cada bloque tenga la estructura correcta
              Object.entries(section.blocks).forEach(([blockId, block]) => {
                  if (!block) {
                      console.warn(`Bloque inválido en ${sectionId}.${blockId}`);
                      section.blocks[blockId] = {
                          block_type: 'unknown',
                          settings: {}
                      };
                  }
                  else {
                      // Asegurarse de que tiene block_type
                      if (!block.block_type) {
                          block.block_type = 'unknown';
                      }

                      // Asegurarse de que tiene settings
                      if (!block.settings) {
                          block.settings = {};
                      }
                  }
              });
          }
      });

      return template;
  }


  // Mostrar mensaje
  function showMessage(type, text) {
      currentMessage = { type, text };

      $message
          .removeClass('sb-message-success sb-message-error sb-message-warning sb-message-info')
          .addClass(`sb-message-${type}`)
          .text(text)
          .show();

      // Ocultar después de un tiempo
      setTimeout(() => {
          $message.hide();
          currentMessage = null;
      }, 5000);
  }

  // Establecer estado de carga
  function setLoading(isLoading) {
      loading = isLoading;

      if (isLoading) {
          $loading.show();
          $builder.hide();
      } else {
          $loading.hide();
          $builder.show();
      }
  }

  // Inicializar al cargar el documento
  $(document).ready(function () {
      init();
  });

})(jQuery);