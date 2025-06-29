// assets/js/scripts.js

$(document).ready(function() {
    // Initialize Bootstrap Carousel
    $('#promoCarousel').carousel({
        interval: 5000, // Auto-slide every 5 seconds
        pause: 'hover'
    });

    // Search Autocomplete
    $("#search").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "products/search_autocomplete.php",
                data: { term: request.term },
                beforeSend: function() {
                    $("#search").addClass('loading');
                },
                success: function(data) {
                    response(data);
                },
                complete: function() {
                    $("#search").removeClass('loading');
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            window.location.href = "products/view_product.php?id=" + ui.item.id;
        }
    });

    // Discount Code Validation
    $('form[action*="apply_discount"]').on('submit', function(e) {
        const code = $('input[name="discount_code"]').val();
        if (!code.match(/^[A-Z0-9]{4,20}$/)) {
            e.preventDefault();
            alert('Discount code must be 4-20 alphanumeric characters.');
            return false;
        }
    });

    // Star Rating for Reviews
    $('.star-rating .star').on('click', function() {
        const rating = $(this).data('value');
        $('#rating').val(rating);
        $('.star-rating .star').removeClass('filled');
        $(this).prevAll().addBack().addClass('filled');
    }).on('mouseover', function() {
        $(this).prevAll().addBack().addClass('filled');
    }).on('mouseout', function() {
        $('.star-rating .star').removeClass('filled');
        const selectedRating = $('#rating').val();
        $('.star-rating .star').slice(0, selectedRating).addClass('filled');
    });

    // Review Form Validation
    $('form[action*="view_product.php"]').on('submit', function(e) {
        const rating = $('#rating').val();
        const comment = $('#comment').val();
        if (!rating) {
            e.preventDefault();
            alert('Please select a rating.');
            return false;
        }
        if (comment.length > 500) {
            e.preventDefault();
            alert('Comment must be 500 characters or less.');
            return false;
        }
    });

    // Quick Add to Cart
    $('.quick-add-to-cart').on('click', function(e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        const quantity = 1; // Default quantity
        $.ajax({
            url: 'cart/add_to_cart.php',
            method: 'GET',
            data: { id: productId, quantity: quantity },
            success: function(response) {
                alert('Product added to cart!');
                // Optionally update cart count in header
                updateCartCount();
            },
            error: function() {
                alert('Failed to add product to cart.');
            }
        });
    });

    // Update Cart Count
    function updateCartCount() {
        $.get('cart/cart_count.php', function(data) {
            $('.cart-count').text(data.count || 0);
        });
    }

    // Confirmation for Delete Actions
    $('a[href*="action=delete"]').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });

    // Order Status Update Validation
    $('form[action*="update_status"]').on('submit', function(e) {
        const status = $(this).find('select[name="status"]').val();
        const tracking = $(this).find('input[name="tracking_number"]').val();
        if (status === 'shipped' && !tracking) {
            e.preventDefault();
            alert('Tracking number is required for shipped status.');
            return false;
        }
    });

    // Smooth Scroll for Anchor Links
    $('a[href*="#"]').on('click', function(e) {
        if (this.hash !== '') {
            e.preventDefault();
            const hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 60
            }, 800);
        }
    });

    // Tooltips for Admin Dashboard
    $('.metric-card').tooltip({
        title: function() {
            return $(this).find('p').text();
        },
        placement: 'top'
    });

    // Lazy Load Images
    const lazyImages = $('.lazy');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });
        lazyImages.each(function() {
            observer.observe(this);
        });
    } else {
        lazyImages.each(function() {
            $(this).attr('src', $(this).data('src')).removeClass('lazy');
        });
    }
});

// Initialize Bootstrap Tooltips
$(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
$(window).scroll(function() {
    if ($(this).scrollTop() > 200) {
        $('#back-to-top').fadeIn();
    } else {
        $('#back-to-top').fadeOut();
    }
});
$('#back-to-top').click(function() {
    $('html, body').animate({ scrollTop: 0 }, 600);
    return false;
});


// calculate shipping cost
$('#postal_code').on('change', function() {
    const postalCode = $(this).val();
    $.get('cart/calculate_shipping.php', { postal_code: postalCode }, function(data) {
        $('.shipping-cost').text('$' + data.cost);
        updateTotal();
    });
});


// Form Validation
$('.needs-validation').on('submit', function(e) {
    const form = this;
    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        $(form).addClass('was-validated');
    }
});

// Clear Validation on Input
$('.needs-validation input, .needs-validation select').on('input', function() {
    $(this).removeClass('is-invalid');
    $(this).next('.invalid-feedback').text('');
});