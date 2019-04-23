---
title: JSON Serialization
subTitle: 
currentMenu: json_serialization
---

By default, beans implement the [JsonSerializable](http://php.net/manual/fr/class.jsonserializable.php) interface.
As such, they can automatically be casted to JSON using the `json_encode` function.

Serialization works this way:

- Simple columns are put in the JSON object directly.
- Date columns are serialized as strings, in the ISO 8601 format.
- If a column is a foreign key, it is not serialized in the JSON object (you will not see the ID of the foreign key in the object).
  Instead, the whole object pointed at is embedded in the JSON serialization. This is however not recursive (inner objects
  of the embedded object are not embedded).
- Finally, many to many relationships are embedded too.

So a typical serialized bean might look like this:

```json
{
    "id": 4,                                   // Primary keys are serialized
    "name": "Bill Shakespeare",                // Normal columns are serialized
    "createdAt": "2015-10-24T13:57:13+00:00",  // Dates are serialized in ISO 8601 format
    "email": "bill@shakespeare.com",
    "country": {                               // Foreign keys (like country_id) are transformed into the represented object 
        "id": "2",                             // Note: foreign keys of embedded objects are ignored.
        "label": "UK"
    },
    "roles": [                                 // Many to many relationships are embedded too.
        {                                      // Note: many to many relationships of embedded objects are ignored.
            "id": 2,
            "name": "Writers"
        }
    ]
}
```

If you want to customize JSON serialization, you have 2 options:

1. you can override the `jsonSerialize` method in each bean class (the method is defined in the abstract bean)
2. you can add JSON annotations to your DB columns comments. [JSON annotations are described in the page dedicated to annotations.](annotations.md#the-json-annotations) 