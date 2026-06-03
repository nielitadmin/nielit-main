f = open('c:/xampp/htdocs/public_html/public/courses.php', 'r', encoding='utf-8')
content = f.read()
f.close()

old = '                                        <?php endif; ?>\n                                    </div>\n                                </div>'

new = '                                        <?php endif; ?>\n                                        <?php if (!empty($row["centre_name"])): ?>\n                                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($row["centre_name"]); ?></span>\n                                        <?php endif; ?>\n                                    </div>\n                                </div>'

matches = content.count(old)
print('Matches found:', matches)

content_new = content.replace(old, new)
print('After replace, fa-building count:', content_new.count('fas fa-building'))

f = open('c:/xampp/htdocs/public_html/public/courses.php', 'w', encoding='utf-8')
f.write(content_new)
f.close()
print('Done')
