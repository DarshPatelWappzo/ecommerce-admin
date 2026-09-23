@if ($errors->any())
    <script>
        jQuery(function($) {
            $(@json($selector)).validate().showErrors(@json(collect($errors->messages())->map(fn($messages) => $messages[0])->all()));
        });
    </script>
@endif
