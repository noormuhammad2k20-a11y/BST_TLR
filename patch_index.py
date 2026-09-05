import re

def main():
    try:
        with open('tess.html', 'r', encoding='utf-8') as f:
            tess = f.read()
    except Exception as e:
        print(f"Error reading tess.html: {e}")
        return

    try:
        with open(r'resources\views\cloth-store\checkout\index.blade.php', 'r', encoding='utf-8') as f:
            index = f.read()
    except Exception as e:
        print(f"Error reading index.blade.php: {e}")
        return

    # 1. Extract style from tess
    style_match = re.search(r'<style>.*?</style>', tess, re.DOTALL)
    tess_style = style_match.group(0) if style_match else '<style></style>'

    # 2. Extract body container from tess
    # The body container starts with <div class="p-4 md:p-6 max-w-[1600px] mx-auto">
    # We want everything inside this div up to the script tag (or end of html)
    body_start_idx = tess.find('<div class="p-4 md:p-6 max-w-[1600px] mx-auto">')
    script_idx = tess.find('<script>', body_start_idx)
    
    if body_start_idx == -1 or script_idx == -1:
        print("Could not find body or script tags in tess.html")
        return
        
    tess_body = tess[body_start_idx:script_idx].strip()
    
    # 3. Add Blade syntax back to tess_body
    
    # a. Category Select
    cat_select_old = '''<select class="input-premium w-full cursor-pointer text-sm">
                            <option>All Categories</option>
                            <option>Fabric</option>
                            <option>Ready-to-Wear</option>
                        </select>'''
    cat_select_new = '''<select id="pos-category-filter" class="input-premium w-full cursor-pointer text-sm">
                            <option value="all">All Categories</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>'''
    tess_body = tess_body.replace(cat_select_old, cat_select_new)
    
    # b. Unit Select
    unit_select_old = '''<select class="input-premium w-full cursor-pointer text-sm">
                            <option>All Units</option>
                            <option>Meters</option>
                            <option>Pieces</option>
                        </select>'''
    unit_select_new = '''<select id="pos-unit-filter" class="input-premium w-full cursor-pointer text-sm">
                            <option value="all">All Units</option>
                            <option value="meter">Meters</option>
                            <option value="pcs">Pieces</option>
                        </select>'''
    tess_body = tess_body.replace(unit_select_old, unit_select_new)
    
    # c. Add Customer Sidebar back into Step 2 Order Summary
    customer_sidebar = """
                    <div class="px-4 pt-4 pb-3 border-b border-slate-200 bg-white shrink-0">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-sm font-bold text-slate-800">Customer</h3>
                            <button onclick="document.getElementById('customer-modal').classList.remove('hidden')" class="text-[11px] font-semibold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded transition">
                                <i class="fa-solid fa-plus mr-1 text-[9px]"></i> New
                            </button>
                        </div>
                        <div class="relative mb-2">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[11px] pointer-events-none"></i>
                            <input type="search" id="pos-customer-search" autocomplete="off" placeholder="Search customer..." class="input-premium w-full pl-8 py-1.5 text-xs">
                        </div>
                        <div class="relative">
                            <select id="pos-customer" class="input-premium w-full appearance-none pr-8 py-1.5 font-semibold text-xs">
                                @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-due="{{ $c->due_balance }}" @selected(in_array(strtolower($c->name), ['walk-in', 'walkin'], true))>{{ $c->name }}{{ $c->phone ? ' (' . $c->phone . ')' : '' }}</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
                        </div>
                        <div id="customer-due-warning" class="hidden mt-2 text-[10px] font-semibold text-rose-600 bg-rose-50 p-1.5 rounded border border-rose-100">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Due: <span id="customer-due-amt" class="cell-num">Rs 0</span>
                        </div>
                    </div>
"""
    # Find the order summary header and insert below the Edit button
    order_summary_header = """<div class="p-4 border-b border-slate-200 shrink-0 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-slate-800">Order Summary</h3>
                        <button onclick="goToStep(1)" class="btn-ghost py-1.5 px-2.5 text-[10px]">
                            <i class="fa-solid fa-arrow-left text-[9px]"></i> Edit
                        </button>
                    </div>"""
    tess_body = tess_body.replace(order_summary_header, customer_sidebar + "\n" + order_summary_header)
    
    # d. Modal form modifications
    tess_body = tess_body.replace('<form id="quick-customer-form" onsubmit="saveCustomer(event)">', 
                                  '<form id="quick-customer-form" onsubmit="submitNewCustomer(event)">\\n                    @csrf')
    tess_body = tess_body.replace('<button onclick="closeModal()"', '<button type="button" onclick="document.getElementById(\'customer-modal\').classList.add(\'hidden\')"')
    
    # e. IDs modifications to match index.blade.php JS logic
    tess_body = tess_body.replace('id="dock-count"', 'id="cart-count-pill"')
    tess_body = tess_body.replace('id="dock-total"', 'id="cart-subtotal"')
    tess_body = tess_body.replace('id="customer-modal"', 'id="new-customer-modal"')
    tess_body = tess_body.replace('id="qc-name"', 'id="qc-name" name="name"')
    tess_body = tess_body.replace('id="qc-phone"', 'id="qc-phone" name="phone"')
    tess_body = tess_body.replace('id="qc-city"', 'id="qc-city" name="city"')
    
    # Wait, the modal inputs in tess.html don't have IDs! Let's fix them with regex
    tess_body = re.sub(r'<input type="text" class="input-premium w-full pl-9 pr-3" placeholder="e.g. Ahmed Khan"\s*required>', 
                       r'<input type="text" id="qc-name" name="name" class="input-premium w-full pl-9 pr-3" placeholder="e.g. Ahmed Khan" required>', tess_body)
    tess_body = re.sub(r'<input type="text" class="input-premium w-full pl-9 pr-3" placeholder="e.g. 03001234567">', 
                       r'<input type="text" id="qc-phone" name="phone" class="input-premium w-full pl-9 pr-3" placeholder="e.g. 03001234567">', tess_body)
    tess_body = re.sub(r'<input type="text" class="input-premium w-full pl-9 pr-3" placeholder="e.g. Karachi">', 
                       r'<input type="text" id="qc-city" name="city" class="input-premium w-full pl-9 pr-3" placeholder="e.g. Karachi">', tess_body)
    
    # Change routing in success page
    tess_body = tess_body.replace('<button class="btn-ghost py-2">\n                                <i class="fa-solid fa-list text-[9px]"></i> Orders\n                            </button>',
                                  '<a href="{{ route(\'cloth-store.orders.index\') }}" class="btn-ghost py-2">\n                                <i class="fa-solid fa-list text-[9px]"></i> Orders\n                            </a>')
    
    tess_body = tess_body.replace('<button class="btn-ghost py-2">\n                                <i class="fa-solid fa-receipt text-[9px]"></i> Receipt\n                            </button>',
                                  '<button onclick="showLastReceipt()" class="btn-ghost py-2">\n                                <i class="fa-solid fa-receipt text-[9px]"></i> Receipt\n                            </button>')
    
    # In Step 2 Payment, link up the methods
    tess_body = tess_body.replace('id="btn-submit-order"', 'id="btn-submit-order"') # already there
    # Replace selected-method hidden input
    tess_body = tess_body.replace('<h2 class="text-sm font-bold text-slate-900 tracking-tight">Payment Method</h2>',
                                  '<h2 class="text-sm font-bold text-slate-900 tracking-tight">Payment Method</h2>\n                        <input type="hidden" id="selected-method" value="Cash">')
    
    # 4. Replace content in index.blade.php
    content_start_str = "@section('content')"
    content_start_idx = index.find(content_start_str)
    
    if content_start_idx == -1:
        print("Could not find @section('content')")
        return
        
    content_end_idx = index.find("<style>", content_start_idx)
    
    new_index = index[:content_start_idx + len(content_start_str)] + "\\n" + tess_body + "\\n\\n" + tess_style + "\\n" + index[content_end_idx + len(tess_style):]
    
    # Actually wait, I shouldn't jump past tess_style, I need to jump past the original style block.
    # Let's do it cleanly using regex.
    # Capture everything between @section('content') and @endsection
    
    match = re.search(r"(@section\('content'\))(.*?)(@endsection)", index, re.DOTALL)
    if match:
        replacement = match.group(1) + "\\n" + tess_body + "\\n\\n" + tess_style + "\\n" + match.group(3)
        new_index = index[:match.start()] + replacement + index[match.end():]
        
        with open(r'resources\views\cloth-store\checkout\index.blade.php', 'w', encoding='utf-8') as f:
            f.write(new_index)
        print("Updated index.blade.php successfully")
    else:
        print("Regex for content section failed")

if __name__ == '__main__':
    main()
