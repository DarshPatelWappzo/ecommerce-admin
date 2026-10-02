<script>
    jQuery(function($) {
        $(@json($validator['selector'])).each(function() {
            const form = $(this);
            form.validate({
                onsubmit: !form.is(
                    '[data-product-form], [data-order-form], [data-order-action]'),
                errorElement: 'span',
                errorClass: 'field-error',
                ignore: @json($validator['ignore']),
                normalizer: function(value) {
                    if (typeof value !== 'string') return value;
                    const normalized = value.trim();
                    if (this.name === 'code') return normalized.toUpperCase();
                    if (this.name === 'email') return normalized.toLowerCase();
                    return normalized;
                },
                errorPlacement: function(error, element) {
                    error.attr('aria-live', 'polite');
                    const select2 = element.next('.select2-container');
                    error.insertAfter(select2.length ? select2 : element);
                },
                highlight: function() {},
                unhighlight: function() {},
                rules: @json($validator['rules'])
            });
            form.find('select[name]').on('change', function() {
                $(this).valid();
            });
        });
    });
</script>
