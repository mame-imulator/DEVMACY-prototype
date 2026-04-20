<?php
// pos.php
$page_title = 'Point of Sale';
include 'includes/header.php';

// Fetch next Receipt Number
$next_receipt = 1;
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT MAX(sale_id) as last_id FROM Sale");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && $res['last_id']) {
            $next_receipt = $res['last_id'] + 1;
        }
    } catch(PDOException $e) {
        $db_error = $e->getMessage();
    }
}
?>

<style>
    .pos-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 20px;
        height: calc(100vh - 120px);
    }
    
    /* Floating Search results */
    .search-container { position: relative; }
    #searchResults {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1000;
        background: var(--surface-color);
        border: 1px solid var(--primary-color);
        border-radius: var(--radius-sm);
        max-height: 400px;
        overflow-y: auto;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        display: none;
    }
</style>

<div class="page-container" style="padding-top: 16px;">
    <div class="pos-grid">
        
        <!-- Section 1: Search -->
        <div class="glass-panel" style="padding: 20px; display: flex; flex-direction: column;">
            <div class="search-container" style="margin-bottom: 20px;">
                <label style="display:block; font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">SEARCH DRUG</label>
                <input type="text" id="productSearch" placeholder="Scan or Type..." 
                       onkeyup="searchProducts(this.value)"
                       onkeydown="if(event.key === 'Enter') handleEnterSearch()"
                       autocomplete="off"
                       style="width: 100%; padding: 14px; border-radius: var(--radius-sm); border: 2px solid var(--border-color); background: var(--bg-color); color: white; font-size: 16px;">
                <div id="searchResults"></div>
            </div>
            
            <div style="flex: 1; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <h4 style="margin-bottom: 12px; font-size: 14px; color: var(--text-muted);">NOTES</h4>
                <textarea placeholder="Sale notes..." style="width: 100%; height: 100px; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 8px; color: white; padding: 12px; font-size: 13px;"></textarea>
            </div>
        </div>
        
        <!-- Section 2: Active Cart -->
        <div class="glass-panel" style="padding: 20px; display: flex; flex-direction: column; background: rgba(30, 41, 59, 0.4);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0;">Active Sale</h3>
                
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button onclick="toggleAIAssistant()" style="display: flex; align-items: center; gap: 8px; background: rgba(99, 102, 241, 0.1); border: 1px solid var(--primary-color); color: var(--primary-color); padding: 10px 18px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; font-size: 13px;">
                        <i class="ph ph-sparkle"></i> Gemini Insights
                    </button>
                    <div style="text-align: right;">
                        <div style="font-size: 11px; color:var(--text-muted); text-transform:uppercase;">Grand Total</div>
                        <div id="totalText" style="font-size: 28px; font-weight: 800; color: var(--secondary-color);">$0.00</div>
                    </div>
                    <button id="payButton" onclick="submitSale()" class="btn btn-primary" style="padding: 12px 24px; font-weight: 800; border-radius: 12px; box-shadow: var(--shadow-glow);">
                        FINALIZE SALE
                    </button>
                </div>
            </div>
            
            <div id="cartWrapper" style="flex: 1; overflow-y: auto; padding-right: 5px;">
                <!-- Cart items -->
                <div style="text-align: center; padding-top: 80px; color: var(--text-muted);">
                    <i class="ph ph-shopping-cart-simple" style="font-size: 64px; opacity: 0.1; margin-bottom: 16px;"></i>
                    <p>Add items to start the sale</p>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
let cart = [];
let currentSearchResults = [];

// Track input for search results
async function searchProducts(query) {
    const resultsBox = document.getElementById('searchResults');
    if (query.trim().length < 1) {
        resultsBox.style.display = 'none';
        return;
    }

    try {
        const response = await fetch(`api/search_products.php?q=${encodeURIComponent(query)}`);
        currentSearchResults = await response.json();
        
        if (currentSearchResults.length === 0) {
            resultsBox.style.display = 'none';
            return;
        }

        let html = '';
        currentSearchResults.forEach((p, index) => {
            const priceHtml = p.has_promo 
                ? `<span style="text-decoration: line-through; color: var(--text-muted); margin-right: 8px;">$${p.original_price.toFixed(2)}</span>
                   <span style="color: var(--secondary-color); font-weight: 800;">$${p.price_per_unit.toFixed(2)}</span>`
                : `<span style="color: var(--secondary-color); font-weight: 700;">$${p.price_per_unit.toFixed(2)}</span>`;
            
            const badgeHtml = p.has_promo 
                ? `<span style="background: var(--accent-color); color: white; font-size: 10px; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; font-weight: 800; margin-left: 8px;">SALE: ${p.promo_name}</span>` 
                : '';

            html += `
            <div class="search-item" onclick="addToCartByIndex(${index})" style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="font-weight: 700; color: white;">${p.product_name} ${badgeHtml}</div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    <span>${p.size_description}</span>
                    <div>${priceHtml}</div>
                </div>
            </div>`;
        });
        
        resultsBox.innerHTML = html;
        resultsBox.style.display = 'block';

        const items = resultsBox.querySelectorAll('.search-item');
        items.forEach(it => {
            it.addEventListener('mouseover', () => it.style.background = 'rgba(79, 70, 229, 0.2)');
            it.addEventListener('mouseout', () => it.style.background = 'transparent');
        });

    } catch (e) {
        console.error("Search failed:", e);
    }
}

// Handle Enter key for search
function handleEnterSearch() {
    if (currentSearchResults.length > 0) {
        addToCartByIndex(0);
    }
}

function addToCartByIndex(index) {
    addToCart(currentSearchResults[index]);
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-container')) {
        document.getElementById('searchResults').style.display = 'none';
    }
});

function addToCart(product) {
    const existing = cart.find(item => item.barcode === product.barcode);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            barcode: product.barcode,
            name: product.product_name,
            size: product.size_description,
            price: parseFloat(product.price_per_unit),
            originalPrice: parseFloat(product.original_price),
            hasPromo: product.has_promo,
            quantity: 1
        });
    }
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('productSearch').value = '';
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function updateQuantity(index, qty) {
    const nQty = parseInt(qty);
    if (isNaN(nQty) || nQty < 1) {
        cart[index].quantity = 1;
    } else {
        cart[index].quantity = nQty;
    }
    renderCart();
}

function renderCart() {
    const wrapper = document.getElementById('cartWrapper');
    if (cart.length === 0) {
        wrapper.innerHTML = '<div style="text-align: center; padding-top: 80px; color: var(--text-muted);"><i class="ph ph-shopping-cart-simple" style="font-size: 64px; opacity: 0.1; margin-bottom: 16px;"></i><p>Add items to start the sale</p></div>';
        document.getElementById('totalText').innerText = '$0.00';
        return;
    }

    let html = '';
    let total = 0;
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        html += `
        <div class="glass-panel" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 16px; border: 1px solid var(--border-color);">
            <div style="flex: 1;">
                <h4 style="margin:0; font-size: 16px;">${item.name}</h4>
                <div style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">
                    ${item.size} @ 
                    ${item.hasPromo ? `<span style="text-decoration: line-through; opacity: 0.6; margin-right: 4px;">$${item.originalPrice.toFixed(2)}</span>` : ''}
                    $${item.price.toFixed(2)}
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <input type="number" value="${item.quantity}" 
                       onchange="updateQuantity(${index}, this.value)"
                       onfocus="this.select()"
                       style="width: 70px; padding: 12px; background: var(--bg-color); border: 1px solid var(--border-color); color: white; border-radius: 12px; text-align: center; font-weight: 800; font-size: 18px;">
                <div style="font-weight: 800; width: 100px; text-align: right; color: var(--secondary-color); font-size: 18px;">$${itemTotal.toFixed(2)}</div>
                <button onclick="removeFromCart(${index})" style="background: none; border: none; color: var(--accent-color); cursor: pointer; padding: 10px;">
                    <i class="ph ph-trash" style="font-size: 22px;"></i>
                </button>
            </div>
        </div>`;
    });
    
    wrapper.innerHTML = html;
    document.getElementById('totalText').innerText = `$${total.toFixed(2)}`;
}

async function submitSale() {
    if (cart.length === 0) {
        alert('Your cart is empty.');
        return;
    }

    const payButton = document.getElementById('payButton');
    const originalText = payButton.innerHTML;
    payButton.disabled = true;
    payButton.innerHTML = '<i class="ph ph-circle-notch-bold"></i> PROCESSING...';

    try {
        const response = await fetch('api/process_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart: cart })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Sale #<?php echo str_pad($next_receipt, 3, '0', STR_PAD_LEFT); ?> completed!');
            cart = [];
            renderCart();
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Failed to connect to server.');
    } finally {
        payButton.disabled = false;
        payButton.innerHTML = originalText;
    }
}
</script>

<?php include 'includes/pos_assistant.php'; ?>
<?php include 'includes/footer.php'; ?>
