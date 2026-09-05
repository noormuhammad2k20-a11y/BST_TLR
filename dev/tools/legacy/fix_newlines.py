import re

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace('\\n<div class="p-4 md:p-6', '\n<div class="p-4 md:p-6')
text = text.replace('\\n@endsection', '\n@endsection')
text = text.replace('</div>\\n\\n<style>', '</div>\n\n<style>')

with open(r'resources\views\cloth-store\checkout\index.blade.php', 'w', encoding='utf-8') as f:
    f.write(text)

print('Fixed literal newlines.')
