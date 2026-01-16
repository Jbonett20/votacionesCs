$(document).ready(function() {
    // Toggle sidebar on mobile
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('active');
    });
    
    // Close sidebar when clicking outside on mobile
    $(document).on('click', function(e) {
        if ($(window).width() <= 991) {
            if (!$(e.target).closest('.sidebar, .toggle-sidebar').length) {
                $('#sidebar').removeClass('active');
            }
        }
    });
    
    // Active menu item
    const currentPage = window.location.pathname.split('/').pop();
    $('.menu-item').each(function() {
        const href = $(this).attr('href');
        if (href === currentPage) {
            $('.menu-item').removeClass('active');
            $(this).addClass('active');
        }
    });
    
    // Animation on scroll
    $(window).on('scroll', function() {
        $('.stat-card').each(function() {
            const elementTop = $(this).offset().top;
            const elementBottom = elementTop + $(this).outerHeight();
            const viewportTop = $(window).scrollTop();
            const viewportBottom = viewportTop + $(window).height();
            
            if (elementBottom > viewportTop && elementTop < viewportBottom) {
                $(this).addClass('animate-in');
            }
        });
    });
});
