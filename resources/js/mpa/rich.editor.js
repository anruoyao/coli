window.addEventListener('load', () => {
	const richEditor = document.getElementById('rich-editor-content');
	const editor = document.getElementById('rich-editor');
	
	if (richEditor && editor) {
		editor.innerHTML = richEditor.value;
		editor.addEventListener('input', () => {
			richEditor.value = editor.innerHTML;
		});
	}
});
