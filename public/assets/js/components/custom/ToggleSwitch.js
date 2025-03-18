/**
 * Toggle Switch Component
 * A styled checkbox that appears as a toggle switch
 */
(function() {
    // Get the base CheckBoxComponent class from Form.io
    const CheckBoxComponent = Formio.Components.components.checkbox;
  
    // Define our component class using ES6 class syntax
    class ToggleSwitchComponent extends CheckBoxComponent {
      // Define the builder info as a static getter
      static get builderInfo() {
        return {
          title: 'Toggle Switch',
          group: 'custom',
          icon: 'toggle-on',
          weight: 30,
          schema: ToggleSwitchComponent.schema()
        };
      }
  
      // Define the schema factory method
      static schema() {
        return CheckBoxComponent.schema({
          type: 'toggleSwitch',
          label: 'Toggle Switch',
          key: 'toggleSwitch',
          customClass: 'form-switch',
          uniqueKey: false,
          input: true,
          tableView: true
        });
      }
  
      // Inherit the edit form from the parent
      static editForm() {
        return CheckBoxComponent.editForm();
      }
  
      // Constructor to initialize the component
      constructor(component, options, data) {
        super(component, options, data);
      }
  
      // Initialize the component after it's created
      init() {
        super.init();
        
        // Ensure the component always has the form-switch class
        if (!this.component.customClass) {
          this.component.customClass = 'form-switch';
        } else if (!this.component.customClass.includes('form-switch')) {
          this.component.customClass += ' form-switch';
        }
      }
  
      // Optional: Override render method to customize appearance
      render(element) {
        const result = super.render(element);
        
        // Additional rendering if needed
        
        return result;
      }
    }
  
    // Register the component
    if (window.ComponentRegistry) {
      window.ComponentRegistry.register('toggleSwitch', ToggleSwitchComponent);
    } else {
      // Fallback to direct registration if ComponentRegistry is unavailable
      Formio.Components.addComponent('toggleSwitch', ToggleSwitchComponent);
    }
  
    // Make it available globally for debugging and manual usage
    window.ToggleSwitchComponent = ToggleSwitchComponent;
  })();