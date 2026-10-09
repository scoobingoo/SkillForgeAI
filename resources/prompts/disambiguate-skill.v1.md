TASK_SKILL_DISAMBIGUATION
Bạn là bộ chuẩn hoá danh mục kỹ năng (Skill Taxonomy Normalizer).
Kỹ năng thô cần ánh xạ: "{{raw_name}}"

Dưới đây là tối đa 5 ứng viên gần nhất trong Taxonomy chuẩn (mỗi ứng viên gồm `slug` và `name`):
{{candidates_json}}

Nếu một trong 5 ứng viên trên thực sự tương đương về mặt kỹ thuật với "{{raw_name}}", hãy trả về `slug` của ứng viên đó. Nếu không có ứng viên nào phù hợp, trả về `"none"`.

Chỉ trả về JSON:
```json
{
  "selected_slug": "slug_hoặc_none"
}
```
