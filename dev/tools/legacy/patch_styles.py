import re

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'r', encoding='utf-8') as f:
    text = f.read()

# 1. Height
text = text.replace('height: calc(100vh - 9.5rem);', 'height: calc(100vh - 10.5rem);')

# 2. Step 2 Proportions
text = text.replace('<div class="w-80 shrink-0 bg-slate-50/50', '<div class="w-80 lg:w-96 shrink-0 bg-slate-50/50')
text = text.replace('<div class="flex-1 flex flex-col bg-white min-h-0 h-full p-5">', '<div class="flex-1 flex flex-col bg-white min-h-0 h-full p-5 lg:p-8">')
text = text.replace('<div class="flex-1 min-h-0 flex flex-col justify-center space-y-4">', '<div class="flex-1 min-h-0 flex flex-col justify-center space-y-5">')

# 3. Step 3 Proportions
text = text.replace('<div class="w-80 shrink-0 bg-white rounded-2xl', '<div class="w-80 lg:w-96 shrink-0 bg-white rounded-2xl')

# 4. Modal Polish
text = text.replace('class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm', 'class="hidden fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-md')
text = text.replace('<div class="modal-in bg-white rounded-xl shadow-2xl w-full max-w-sm', '<div class="modal-in bg-white rounded-2xl shadow-2xl w-full max-w-md')
text = text.replace('<div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">', '<div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">')
text = text.replace('<div class="p-5 space-y-4">', '<div class="p-6 space-y-5">')
text = text.replace('<div class="p-5 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">', '<div class="p-6 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">')
text = text.replace('focus:ring-indigo-100', 'focus:ring-indigo-500/20')

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'w', encoding='utf-8') as f:
    f.write(text)

print('Updated index.blade.php styles!')
