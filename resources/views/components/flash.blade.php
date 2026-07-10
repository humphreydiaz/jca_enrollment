@if (session('status'))
    <div class="jca-status jca-status-ready" role="status">
        <span aria-hidden="true">&#10003;</span>
        {{ session('status') }}
    </div>
@endif
