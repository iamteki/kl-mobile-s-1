// ===================================
// resources/js/modules/filters.js
// Category page filters functionality
// ===================================

export function initFilters() {
    const filtersColumn = document.querySelector('.filters-column');
    const filtersOverlay = document.querySelector('.filters-overlay');
    const mobileFilterToggle = document.querySelector('.mobile-filter-toggle');
    
    if (!filtersColumn) return;

    // Mobile filters toggle
    window.toggleFilters = function() {
        filtersColumn.classList.toggle('show');
        filtersOverlay.classList.toggle('show');
    };

    // Price range filter
    const priceInputs = document.querySelectorAll('.price-range input');
    const applyPriceBtn = document.querySelector('.price-range-apply');
    
    if (applyPriceBtn) {
        applyPriceBtn.addEventListener('click', function() {
            const minPrice = priceInputs[0].value;
            const maxPrice = priceInputs[1].value;
            applyFilters();
        });
    }

    // Checkbox filters
    const filterCheckboxes = document.querySelectorAll('.filter-group input[type="checkbox"]');
    filterCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', applyFilters);
    });

    // Clear filters
    const clearFiltersBtn = document.querySelector('.clear-filters-btn');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearAllFilters);
    }

    function applyFilters() {
        const filters = collectFilters();
        const url = new URL(window.location);
        
        // Update URL parameters
        Object.keys(filters).forEach(key => {
            if (filters[key].length > 0) {
                url.searchParams.set(key, filters[key].join(','));
            } else {
                url.searchParams.delete(key);
            }
        });
        
        // Update page without reload
        window.history.pushState({}, '', url);
        
        // Trigger AJAX load
        loadFilteredProducts(filters);
    }

    function collectFilters() {
        const filters = {
            subcategory: [],
            brand: [],
            power_output: [],
            availability: []
        };

        // Collect checked filters
        filterCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const filterType = checkbox.closest('.filter-group').dataset.filterType;
                if (filterType && filters[filterType]) {
                    filters[filterType].push(checkbox.value);
                }
            }
        });

        // Add price range
        const minPrice = document.querySelector('input[name="min_price"]')?.value;
        const maxPrice = document.querySelector('input[name="max_price"]')?.value;
        
        if (minPrice) filters.min_price = minPrice;
        if (maxPrice) filters.max_price = maxPrice;

        return filters;
    }

    function loadFilteredProducts(filters) {
        const productsContainer = document.querySelector('.products-grid');
        if (!productsContainer) return;

        // Show loading state
        productsContainer.style.opacity = '0.5';

        // Make AJAX request
        fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ filters })
        })
        .then(response => response.json())
        .then(data => {
            productsContainer.innerHTML = data.html;
            productsContainer.style.opacity = '1';
            updateResultsCount(data.count);
        })
        .catch(error => {
            console.error('Filter error:', error);
            productsContainer.style.opacity = '1';
        });
    }

    function clearAllFilters() {
        filterCheckboxes.forEach(checkbox => checkbox.checked = false);
        priceInputs.forEach(input => input.value = '');
        applyFilters();
    }

    function updateResultsCount(count) {
        const resultsCount = document.querySelector('.results-count');
        if (resultsCount) {
            resultsCount.textContent = `Showing ${count} results`;
        }
    }
}