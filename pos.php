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

    /* Floating Widget System */
    .float-widget {
        position: fixed;
        background: #0f172a;
        border: 1px solid var(--primary-color);
        border-radius: 12px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.8);
        z-index: 9000;
        display: none;
        flex-direction: column;
        overflow: hidden;
    }
    .widget-header {
        background: var(--surface-light);
        padding: 10px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: move;
        border-bottom: 1px solid var(--border-color);
        user-select: none;
    }
    .widget-content { padding: 16px; }

    /* Float Toolbar */
    .float-toolbar {
        position: fixed;
        bottom: 24px;
        right: 24px;
        display: flex;
        gap: 12px;
        background: rgba(30, 41, 59, 0.8);
        backdrop-filter: blur(10px);
        padding: 10px;
        border-radius: 40px;
        border: 1px solid var(--border-color);
        z-index: 9999;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .tool-btn {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: none;
        background: var(--surface-light);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 18px;
    }
    .tool-btn:hover { background: var(--primary-color); transform: scale(1.1); }
    .tool-btn.active { background: var(--primary-color); box-shadow: var(--shadow-glow); }

    /* Specific Widget Sizing */
    #numPadWidget { width: 280px; bottom: 100px; right: 24px; }
    #keyboardWidget { width: 750px; bottom: 100px; right: 320px; }

    /* Num Pad buttons */
    .numpad {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    .numpad button {
        padding: 15px;
        font-size: 18px;
        font-weight: 700;
        background: var(--surface-light);
        border: 1px solid var(--border-color);
        color: white;
        border-radius: 8px;
        cursor: pointer;
    }
    .numpad button:active { background: var(--primary-color); }
    
    /* Keyboard Styles (Adjusted for widget) */
    .kb-row { display: flex; justify-content: center; gap: 4px; margin-bottom: 6px; }
    .kb-key {
        background: var(--surface-light);
        border: 1px solid var(--border-color);
        color: white;
        padding: 10px;
        border-radius: 6px;
        min-width: 40px;
        font-weight: 700;
        cursor: pointer;
    }
    .kb-key.wide { min-width: 70px; }
    .kb-key.space { width: 300px; }
</style>

<div class="page-container" style="padding-top: 16px;">

    <!-- Floating Toolbar -->
    <div class="float-toolbar">
        <button class="tool-btn" id="btn-numpad" onclick="toggleWidget('numPadWidget', this)" title="Open Number Pad">
            <i class="ph ph-hash"></i>
        </button>
        <button class="tool-btn" id="btn-keyboard" onclick="toggleWidget('keyboardWidget', this)" title="Open English Keyboard">
            <i class="ph ph-keyboard"></i>
        </button>
    </div>

    <!-- Floating Num Pad -->
    <div id="numPadWidget" class="float-widget">
        <div class="widget-header">
            <span style="font-size: 12px; font-weight: 800; color:var(--text-muted);"><i class="ph ph-hash"></i> NUM PAD</span>
            <i class="ph ph-x" onclick="toggleWidget('numPadWidget', document.getElementById('btn-numpad'))" style="cursor:pointer;"></i>
        </div>
        <div class="widget-content">
            <div class="numpad">
                <button type="button" onclick="numpadInit('7')">7</button>
                <button type="button" onclick="numpadInit('8')">8</button>
                <button type="button" onclick="numpadInit('9')">9</button>
                <button type="button" onclick="numpadInit('4')">4</button>
                <button type="button" onclick="numpadInit('5')">5</button>
                <button type="button" onclick="numpadInit('6')">6</button>
                <button type="button" onclick="numpadInit('1')">1</button>
                <button type="button" onclick="numpadInit('2')">2</button>
                <button type="button" onclick="numpadInit('3')">3</button>
                <button type="button" onclick="numpadInit('0')">0</button>
                <button type="button" onclick="numpadInit('back')" style="color:var(--accent-color);"><i class="ph ph-backspace"></i></button>
                <button type="button" onclick="numpadInit('clear')" style="color:var(--accent-color);">C</button>
            </div>
        </div>
    </div>

    <!-- Floating Keyboard -->
    <div id="keyboardWidget" class="float-widget">
        <div class="widget-header">
            <span style="font-size: 12px; font-weight: 800; color:var(--text-muted);"><i class="ph ph-keyboard"></i> ENGLISH KEYBOARD</span>
            <i class="ph ph-x" onclick="toggleWidget('keyboardWidget', document.getElementById('btn-keyboard'))" style="cursor:pointer;"></i>
        </div>
        <div class="widget-content">
            <div class="kb-row">
                <button class="kb-key" onclick="kbInput('q')">Q</button>
                <button class="kb-key" onclick="kbInput('w')">W</button>
                <button class="kb-key" onclick="kbInput('e')">E</button>
                <button class="kb-key" onclick="kbInput('r')">R</button>
                <button class="kb-key" onclick="kbInput('t')">T</button>
                <button class="kb-key" onclick="kbInput('y')">Y</button>
                <button class="kb-key" onclick="kbInput('u')">U</button>
                <button class="kb-key" onclick="kbInput('i')">I</button>
                <button class="kb-key" onclick="kbInput('o')">O</button>
                <button class="kb-key" onclick="kbInput('p')">P</button>
            </div>
            <div class="kb-row">
                <button class="kb-key" onclick="kbInput('a')">A</button>
                <button class="kb-key" onclick="kbInput('s')">S</button>
                <button class="kb-key" onclick="kbInput('d')">D</button>
                <button class="kb-key" onclick="kbInput('f')">F</button>
                <button class="kb-key" onclick="kbInput('g')">G</button>
                <button class="kb-key" onclick="kbInput('h')">H</button>
                <button class="kb-key" onclick="kbInput('j')">J</button>
                <button class="kb-key" onclick="kbInput('k')">K</button>
                <button class="kb-key" onclick="kbInput('l')">L</button>
            </div>
            <div class="kb-row">
                <button id="kbShift" class="kb-key wide" onclick="kbToggleShift()">SHIFT</button>
                <button class="kb-key" onclick="kbInput('z')">Z</button>
                <button class="kb-key" onclick="kbInput('x')">X</button>
                <button class="kb-key" onclick="kbInput('c')">C</button>
                <button class="kb-key" onclick="kbInput('v')">V</button>
                <button class="kb-key" onclick="kbInput('b')">B</button>
                <button class="kb-key" onclick="kbInput('n')">N</button>
                <button class="kb-key" onclick="kbInput('m')">M</button>
                <button class="kb-key wide" onclick="kbInput('back')">BACK</button>
            </div>
            <div class="kb-row">
                <button class="kb-key wide" onclick="kbInput('clear')">CLEAR</button>
                <button class="kb-key space" onclick="kbInput(' ')">SPACE</button>
                <button class="kb-key wide" style="background:var(--primary-color)" onclick="toggleWidget('keyboardWidget', document.getElementById('btn-keyboard'))">DONE</button>
            </div>
        </div>
    </div>

    <div class="pos-grid">
        
        <!-- Section 1: Search -->
        <div class="glass-panel" style="padding: 20px; display: flex; flex-direction: column;">
            <div class="search-container" style="margin-bottom: 20px;">
                <label style="display:block; font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">SEARCH DRUG</label>
                <input type="text" id="productSearch" placeholder="Scan or Type..." 
                       onkeyup="searchProducts(this.value)"
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
                
                <div style="display: flex; align-items: center; gap: 24px;">
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
            </div>
        </div>
        
    </div>
</div>

<script>
let cart = [];
let activeInput = null;
let isShift = false;
let zIndexCounter = 10000;

// Track which input is active
document.addEventListener('focusin', (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        activeInput = e.target;
    }
});

// Generic Toggle for Floating Widgets
function toggleWidget(id, btn) {
    const el = document.getElementById(id);
    const isOpen = el.style.display === 'flex';
    
    if (isOpen) {
        el.style.display = 'none';
        btn.classList.remove('active');
    } else {
        el.style.display = 'flex';
        btn.classList.add('active');
        bringToFront(el);
    }
}

function bringToFront(el) {
    zIndexCounter++;
    el.style.zIndex = zIndexCounter;
}

// Draggable Helper
function initDraggable(el) {
    const header = el.querySelector('.widget-header');
    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;

    header.onmousedown = dragMouseDown;

    function dragMouseDown(e) {
        e = e || window.event;
        e.preventDefault();
        bringToFront(el);
        pos3 = e.clientX;
        pos4 = e.clientY;
        document.onmouseup = closeDragElement;
        document.onmousemove = elementDrag;
    }

    function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        el.style.top = (el.offsetTop - pos2) + "px";
        el.style.left = (el.offsetLeft - pos1) + "px";
        el.style.bottom = 'auto';
        el.style.right = 'auto';
    }

    function closeDragElement() {
        document.onmouseup = null;
        document.onmousemove = null;
    }
}

// Initialize dragging for widgets
document.addEventListener('DOMContentLoaded', () => {
    initDraggable(document.getElementById('numPadWidget'));
    initDraggable(document.getElementById('keyboardWidget'));
});

// Virtual Keyboard Logic
function kbToggleShift() {
    isShift = !isShift;
    const btn = document.getElementById('kbShift');
    btn.style.background = isShift ? 'var(--primary-color)' : 'var(--surface-light)';
    
    document.querySelectorAll('.kb-key').forEach(k => {
        if (k.innerText.length === 1) {
            k.innerText = isShift ? k.innerText.toUpperCase() : k.innerText.toLowerCase();
        }
    });
}

function kbInput(val) {
    if (!activeInput) return;
    
    if (val === 'clear') {
        activeInput.value = '';
    } else if (val === 'back') {
        activeInput.value = activeInput.value.slice(0, -1);
    } else {
        let char = val;
        if (isShift && val.length === 1) char = val.toUpperCase();
        activeInput.value += char;
    }
    
    activeInput.dispatchEvent(new Event('input'));
    activeInput.dispatchEvent(new Event('keyup'));
    activeInput.dispatchEvent(new Event('change'));
    activeInput.focus();
}

function numpadInit(val) {
    if (!activeInput) return;
    
    if (val === 'clear') {
        activeInput.value = '';
    } else if (val === 'back') {
        activeInput.value = activeInput.value.slice(0, -1);
    } else {
        activeInput.value += val;
    }
    
    activeInput.dispatchEvent(new Event('change'));
    activeInput.focus();
}

let currentSearchResults = [];

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
            html += `
            <div class="search-item" onclick="addToCartByIndex(${index})" style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s;">
                <div style="font-weight: 700; color: white;">${p.product_name}</div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    <span>${p.size_description}</span>
                    <span style="color: var(--secondary-color); font-weight: 700;">$${parseFloat(p.price_per_unit).toFixed(2)}</span>
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
        console.error(e);
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
                    ${item.size} @ $${item.price.toFixed(2)}
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

<?php include 'includes/footer.php'; ?>
