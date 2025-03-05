// Delete modal handler
$('#deleteModal').on('show.bs.modal', function (event) {
    const button = $(event.relatedTarget);
    const formId = button.data('formid');
    const formName = button.data('formname');
    
    const modal = $(this);
    modal.find('#formNameToDelete').text(formName);
    modal.find('#formIdToDelete').val(formId);
});

// Search functionality - with null check
const searchBox = document.getElementById('formSearch');
if (searchBox) {
    searchBox.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#formsList tr');
        
        rows.forEach(row => {
            const formId = row.querySelector('td:first-child').textContent.toLowerCase();
            const formName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            
            if (formId.includes(searchTerm) || formName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}