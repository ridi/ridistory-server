CREATE TRIGGER trigger_insert
 AFTER INSERT ON user_part_like
 FOR EACH ROW
 UPDATE part SET num_likes = num_likes + 1 WHERE id = NEW.p_id;

CREATE TRIGGER trigger_delete
 AFTER DELETE ON user_part_like
 FOR EACH ROW
 UPDATE part SET num_likes = num_likes - 1 WHERE id = OLD.p_id;