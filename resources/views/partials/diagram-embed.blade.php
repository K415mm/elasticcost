<div class="position-relative w-100" style="height: 75vh; min-height: 600px;">
    <iframe id="drawio-iframe" class="w-100 h-100 border-0 rounded" src="https://embed.diagrams.net/?embed=1&ui=atlas&spin=1&proto=json" allow="fullscreen"></iframe>
    
    <div id="drawio-loader" class="position-absolute top-50 start-50 translate-middle text-center bg-dark bg-opacity-75 p-4 rounded border border-secondary border-opacity-30" style="z-index: 100;">
        <div class="spinner-border text-theme mb-2" role="status"></div>
        <div class="text-white small fw-bold">Initializing Draw.io Editor...</div>
    </div>
</div>
 
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const iframe = document.getElementById('drawio-iframe');
        const loader = document.getElementById('drawio-loader');
        const xmlData = {!! json_encode($xml) !!};
        const saveUrl = "{{ $saveUrl }}";
        const exitUrl = "{{ $exitUrl }}";
 
        // Helper to send message to iframe
        function sendMsg(action, data = {}) {
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage(JSON.stringify(Object.assign({ action: action }, data)), '*');
            }
        }
 
        // Listen for draw.io messages
        window.addEventListener('message', function(event) {
            if (event.origin !== 'https://embed.diagrams.net') return;
            
            let msg = {};
            try {
                msg = JSON.parse(event.data);
            } catch(e) {
                return;
            }
 
            if (msg.event === 'init') {
                // Hide loader
                if (loader) loader.style.display = 'none';
                
                // Load diagram XML
                sendMsg('load', { xml: xmlData, autosave: 1 });
            } else if (msg.event === 'save') {
                // Show saving status in draw.io
                sendMsg('status', { message: 'Saving diagram...', modified: true });
                
                // Send AJAX to backend
                fetch(saveUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        content: msg.xml
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        sendMsg('status', { message: 'Saved successfully', modified: false });
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: 'Diagram saved successfully.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000,
                            background: '#1e293b',
                            color: '#fff'
                        });
                    } else {
                        sendMsg('status', { message: 'Save failed!', modified: true });
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to save diagram: ' + (data.message || 'Unknown error'),
                            background: '#1e293b',
                            color: '#fff'
                        });
                    }
                })
                .catch(error => {
                    sendMsg('status', { message: 'Error saving!', modified: true });
                    console.error('Save error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Network Error',
                        text: 'Connection error while saving diagram.',
                        background: '#1e293b',
                        color: '#fff'
                    });
                });
            } else if (msg.event === 'exit') {
                window.location.href = exitUrl;
            }
        });
    });
</script>
@endpush
