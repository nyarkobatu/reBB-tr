/**
 * editor.js - Custom extensions for Form.io component editor
 * 
 * This file modifies the Form.io component editor to:
 * 1. Add a "Preserve Key" checkbox in the API tab
 * 2. Add a new "Custom" tab with demo content
 */

(function() {
    // Store the original editForm functions before we override them
    const originalComponentEditForm = Formio.Components.components.component.editForm;
    window.editorExtensionsLoaded = "init";
    
    // Override the base component editForm
    Formio.Components.components.component.editForm = function() {
        // Get the original edit form configuration
        const editForm = originalComponentEditForm.call(this);
        
        // PART 1: Add "Preserve Key" checkbox to the API tab
        // Find the api tab in the edit form
        const apiTab = editForm.components.find(tab => 
            tab.key === 'tabs' && 
            tab.components.find(c => c.key === 'api')
        );
        
        if (apiTab) {
            // Get the actual API tab panel
            const apiPanel = apiTab.components.find(c => c.key === 'api');
            
            if (apiPanel && apiPanel.components) {
                // Find the key field in the API tab to position our checkbox after it
                const keyFieldIndex = apiPanel.components.findIndex(c => c.key === 'key');
                
                if (keyFieldIndex !== -1) {
                    // Create our Preserve Key checkbox
                    const preserveKeyCheckbox = {
                        type: 'checkbox',
                        input: true,
                        key: 'uniqueKey',
                        label: 'Preserve Key',
                        tooltip: 'When enabled, the key will not be regenerated when the label changes.',
                        weight: apiPanel.components[keyFieldIndex].weight + 1, // Position right after the key field
                        defaultValue: false
                    };
                    
                    // Insert the checkbox after the key field
                    apiPanel.components.splice(keyFieldIndex + 1, 0, preserveKeyCheckbox);
                }
            }
        }
        
        /*
        if (apiTab) {
            // Create a new custom tab
            const customTab = {
                key: 'custom',
                label: 'Custom',
                weight: 70, // Position after other tabs
                components: [
                    {
                        type: 'htmlelement',
                        tag: 'div',
                        content: '<h3>Hello World</h3><p>This is a custom tab in the component editor.</p>',
                        className: 'custom-tab-content'
                    },
                    {
                        type: 'textfield',
                        key: 'customField',
                        label: 'Custom Field',
                        placeholder: 'This is just a demo field',
                        input: true
                    }
                ]
            };
            
            
            // Add the custom tab to the tabs component
            apiTab.components.push(customTab);
        }*/
        
        return editForm;
    };
    
    // Apply these changes to all component types that have an edit form
    Object.keys(Formio.Components.components).forEach(type => {
        const component = Formio.Components.components[type];
        
        // Skip the base component as we've already modified it
        if (component !== Formio.Components.components.component && component.editForm) {
            // Store the original editForm for this specific component type
            const originalEditForm = component.editForm;
            
            // Override the editForm method
            component.editForm = function() {
                // Call the original editForm method first
                const editForm = originalEditForm.call(this);
                
                // Now apply our custom modifications
                
                // Find the tabs component
                const tabsComponent = editForm.components.find(c => c.key === 'tabs');
                
                if (tabsComponent) {
                    // Find the API tab
                    const apiTab = tabsComponent.components.find(c => c.key === 'api');
                    
                    if (apiTab && apiTab.components) {
                        // Find the key field
                        const keyFieldIndex = apiTab.components.findIndex(c => c.key === 'key');
                        
                        if (keyFieldIndex !== -1) {
                            // Check if Preserve Key already exists
                            const preserveKeyExists = apiTab.components.some(c => c.key === 'uniqueKey');
                            
                            if (!preserveKeyExists) {
                                // Add the Preserve Key checkbox
                                const preserveKeyCheckbox = {
                                    type: 'checkbox',
                                    input: true,
                                    key: 'uniqueKey',
                                    label: 'Preserve Key',
                                    tooltip: 'When enabled, the key will not be regenerated when the label changes.',
                                    weight: apiTab.components[keyFieldIndex].weight + 1,
                                    defaultValue: false
                                };
                                
                                // Insert after the key field
                                apiTab.components.splice(keyFieldIndex + 1, 0, preserveKeyCheckbox);
                            }
                        }
                    }
                    
                    // Check if the custom tab already exists
                    /*
                    const customTabExists = tabsComponent.components.some(c => c.key === 'custom');
                    
                    if (!customTabExists) {
                        // Add the custom tab
                        tabsComponent.components.push({
                            key: 'custom',
                            label: 'Custom',
                            weight: 70,
                            components: [
                                {
                                    type: 'htmlelement',
                                    tag: 'div',
                                    content: '<h3>Hello World</h3><p>This is a custom tab in the component editor.</p>',
                                    className: 'custom-tab-content'
                                },
                                {
                                    type: 'textfield',
                                    key: 'customField',
                                    label: 'Custom Field',
                                    placeholder: 'This is just a demo field',
                                    input: true
                                }
                            ]
                        });
                    }*/
                   
                }
                
                return editForm;
            };
        }
    });
    
    console.log('Form.io component editor successfully extended with custom features');
})();